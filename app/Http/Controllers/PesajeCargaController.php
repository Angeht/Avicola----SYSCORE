<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePesajesCargaRequest;
use App\Http\Requests\UpdatePesajeCargaRequest;
use App\Models\CargaProveedor;
use App\Models\PagoProveedor;
use App\Models\PesajeCarga;
use App\Models\Producto;
use App\Models\TipoJaba;
use App\Models\Usuario;
use App\Services\AutorizacionEdicionPesajeCarga;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PesajeCargaController extends Controller
{
    public function create(CargaProveedor $cargaProveedor): View
    {
        abort_if($cargaProveedor->estaAnulada(), 409, 'No se pueden agregar pesajes a una carga anulada.');
        abort_if($cargaProveedor->tienePagosVigentes(), 409, 'No se pueden agregar pesajes a una carga con pagos vigentes.');

        $cargaProveedor->load([
            'proveedor:id,nombre_razon_social,nro_documento',
            'producto:id,nombre',
        ]);

        return view('cargas-proveedor.pesajes.create', [
            'crateTypes' => $this->activeCrateTypes(),
            'load' => $cargaProveedor,
            'summary' => DB::table('vw_resumen_carga')
                ->where('carga_id', $cargaProveedor->getKey())
                ->firstOrFail(),
        ]);
    }

    public function store(StorePesajesCargaRequest $request, CargaProveedor $cargaProveedor): RedirectResponse
    {
        $validated = $request->validated();

        $registeredWeighings = DB::transaction(function () use ($cargaProveedor, $validated): int {
            $load = CargaProveedor::query()
                ->whereKey($cargaProveedor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($load->estaAnulada()) {
                throw ValidationException::withMessages([
                    'pesajes' => 'No se pueden agregar pesajes a una carga anulada.',
                ]);
            }

            Producto::query()
                ->whereKey($load->producto_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureLoadHasNoActivePayments(
                $load,
                'pesajes',
                'No se pueden agregar pesajes porque la carga ya tiene pagos vigentes.',
            );

            $crateTypeIds = collect($validated['pesajes'])
                ->filter(fn (array $weighing): bool => $weighing['cantidad_jabas'] > 0)
                ->pluck('tipo_jaba_id')
                ->unique()
                ->values();
            $crateTypes = TipoJaba::query()
                ->whereKey($crateTypeIds)
                ->where('activo', true)
                ->lockForUpdate()
                ->get(['id']);

            if ($crateTypes->count() !== $crateTypeIds->count()) {
                throw ValidationException::withMessages([
                    'pesajes' => 'Uno de los tipos de jaba ya no está disponible.',
                ]);
            }

            $load->pesajes()->createMany(collect($validated['pesajes'])
                ->map(fn (array $weighing): array => [
                    'tipo_jaba_id' => $weighing['cantidad_jabas'] > 0 ? $weighing['tipo_jaba_id'] : null,
                    'cantidad_jabas' => $weighing['cantidad_jabas'],
                    'cantidad_pollos' => $weighing['cantidad_pollos'],
                    'peso_bruto_kg' => $weighing['peso_bruto_kg'],
                    'tara_unitaria_aplicada_kg' => $weighing['cantidad_jabas'] > 0
                        ? $weighing['tara_unitaria_aplicada_kg']
                        : 0,
                    'observacion' => $weighing['observacion'],
                ])
                ->all());

            $this->recalculateTotalCost($load);

            return count($validated['pesajes']);
        }, 3);

        return to_route('cargas-proveedor.show', $cargaProveedor)
            ->with('status', "$registeredWeighings pesaje(s) registrado(s). El costo total fue actualizado.");
    }

    public function edit(
        Request $request,
        CargaProveedor $cargaProveedor,
        PesajeCarga $pesaje,
        AutorizacionEdicionPesajeCarga $authorization,
    ): View|RedirectResponse {
        $this->ensureLoadCanBeEdited($cargaProveedor);

        $administrator = $authorization->administrador($request, $pesaje);

        if (! $administrator instanceof Usuario) {
            return to_route('cargas-proveedor.pesajes.autorizacion.create', [$cargaProveedor, $pesaje])
                ->withErrors(['autorizacion' => 'Valida un PIN administrativo antes de editar este pesaje.']);
        }

        $cargaProveedor->load([
            'proveedor:id,nombre_razon_social',
            'producto:id,nombre',
        ]);
        $pesaje->load('tipoJaba:id,nombre');

        return view('cargas-proveedor.pesajes.edit', [
            'administrator' => $administrator,
            'crateTypes' => $this->activeCrateTypes(),
            'load' => $cargaProveedor,
            'weighing' => $pesaje,
        ]);
    }

    public function update(
        UpdatePesajeCargaRequest $request,
        CargaProveedor $cargaProveedor,
        PesajeCarga $pesaje,
        AutorizacionEdicionPesajeCarga $authorization,
    ): RedirectResponse {
        $administrator = $authorization->administrador($request, $pesaje);

        if (! $administrator instanceof Usuario) {
            return to_route('cargas-proveedor.pesajes.autorizacion.create', [$cargaProveedor, $pesaje])
                ->withErrors(['autorizacion' => 'La autorización venció. Valida nuevamente el PIN administrativo.']);
        }

        $operator = $request->user();
        abort_unless($operator instanceof Usuario, 403);

        $validated = $request->validated();

        DB::transaction(function () use ($administrator, $cargaProveedor, $operator, $pesaje, $validated): void {
            $load = CargaProveedor::query()
                ->whereKey($cargaProveedor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($load->estaAnulada()) {
                throw ValidationException::withMessages([
                    'pesaje' => 'No se puede editar un pesaje de una carga anulada.',
                ]);
            }

            Producto::query()
                ->whereKey($load->producto_id)
                ->lockForUpdate()
                ->firstOrFail();
            $activePaymentTotal = $this->activePaymentTotal($load);

            $lockedWeighing = PesajeCarga::query()
                ->whereKey($pesaje->getKey())
                ->where('carga_id', $load->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($validated['cantidad_jabas'] > 0) {
                $crateType = TipoJaba::query()
                    ->whereKey($validated['tipo_jaba_id'])
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first(['id']);

                if (! $crateType instanceof TipoJaba) {
                    throw ValidationException::withMessages([
                        'tipo_jaba_id' => 'El tipo de jaba seleccionado ya no está disponible.',
                    ]);
                }
            }

            $lockedWeighing->update([
                'tipo_jaba_id' => $validated['cantidad_jabas'] > 0 ? $validated['tipo_jaba_id'] : null,
                'cantidad_jabas' => $validated['cantidad_jabas'],
                'cantidad_pollos' => $validated['cantidad_pollos'],
                'peso_bruto_kg' => $validated['peso_bruto_kg'],
                'tara_unitaria_aplicada_kg' => $validated['cantidad_jabas'] > 0
                    ? $validated['tara_unitaria_aplicada_kg']
                    : 0,
                'observacion' => $validated['observacion'],
                'editado_por' => $operator->getKey(),
                'autorizado_por' => $administrator->getKey(),
                'editado_at' => now(),
            ]);

            $totalCost = $this->recalculateTotalCost($load);

            if ($totalCost->isLessThan($activePaymentTotal)) {
                throw ValidationException::withMessages([
                    'pesaje' => 'El costo total corregido no puede ser menor que el total ya pagado (S/ '.$activePaymentTotal->toScale(2).').',
                ]);
            }
        }, 3);

        $authorization->revocar($request, $pesaje);

        return to_route('cargas-proveedor.show', $cargaProveedor)
            ->with('status', 'Pesaje actualizado con autorización administrativa. El costo total fue recalculado.');
    }

    /**
     * @return Collection<int, TipoJaba>
     */
    private function activeCrateTypes(): Collection
    {
        return TipoJaba::query()
            ->select(['id', 'nombre', 'tara_referencial_kg'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    private function ensureLoadCanBeEdited(CargaProveedor $load): void
    {
        abort_if($load->estaAnulada(), 409, 'No se puede editar un pesaje de una carga anulada.');
    }

    private function activePaymentTotal(CargaProveedor $load): BigDecimal
    {
        return PagoProveedor::query()
            ->where('carga_id', $load->getKey())
            ->vigentes()
            ->lockForUpdate()
            ->get(['monto'])
            ->reduce(
                fn (BigDecimal $total, PagoProveedor $payment): BigDecimal => $total->plus($payment->monto),
                BigDecimal::zero(),
            );
    }

    private function ensureLoadHasNoActivePayments(CargaProveedor $load, string $errorKey, string $message): void
    {
        $activePayments = PagoProveedor::query()
            ->where('carga_id', $load->getKey())
            ->vigentes()
            ->lockForUpdate()
            ->get(['id']);

        if ($activePayments->isNotEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function recalculateTotalCost(CargaProveedor $load): BigDecimal
    {
        $netWeight = PesajeCarga::query()
            ->where('carga_id', $load->getKey())
            ->lockForUpdate()
            ->get(['cantidad_jabas', 'peso_bruto_kg', 'tara_unitaria_aplicada_kg'])
            ->reduce(
                fn (BigDecimal $total, PesajeCarga $weighing): BigDecimal => $total->plus(
                    BigDecimal::of($weighing->peso_bruto_kg)->minus(
                        BigDecimal::of($weighing->tara_unitaria_aplicada_kg)
                            ->multipliedBy($weighing->cantidad_jabas),
                    ),
                ),
                BigDecimal::zero(),
            );
        $totalCost = $netWeight
            ->multipliedBy($load->costo_kg)
            ->toScale(2, RoundingMode::HalfUp);

        if ($totalCost->isGreaterThan('999999999999.99')) {
            throw ValidationException::withMessages([
                'pesajes' => 'El costo acumulado de la carga supera el máximo permitido.',
            ]);
        }

        $load->update(['costo_total' => (string) $totalCost]);

        return $totalCost;
    }
}
