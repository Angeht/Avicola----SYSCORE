<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAjusteProveedorRequest;
use App\Models\AjusteMercaderia;
use App\Models\AjusteProveedor;
use App\Models\CargaProveedor;
use App\Models\Producto;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AjusteProveedorController extends Controller
{
    public function create(CargaProveedor $cargaProveedor): View
    {
        $this->ensureLoadCanBeAdjusted($cargaProveedor);
        $cargaProveedor->load(['proveedor:id,nombre_razon_social,nro_documento', 'producto:id,nombre,modalidad_venta']);

        return view('ajustes-proveedores.create', [
            'availableReturn' => $this->availableReturn($cargaProveedor),
            'balance' => DB::table('vw_saldos_carga_proveedor')->where('carga_id', $cargaProveedor->getKey())->firstOrFail(),
            'load' => $cargaProveedor,
            'stock' => DB::table('vw_saldo_mercaderia_actual')->where('producto_id', $cargaProveedor->producto_id)->first(),
        ]);
    }

    public function store(StoreAjusteProveedorRequest $request, CargaProveedor $cargaProveedor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        $adjustment = DB::transaction(function () use ($cargaProveedor, $user, $validated): AjusteProveedor {
            $load = CargaProveedor::query()->whereKey($cargaProveedor->getKey())->lockForUpdate()->firstOrFail();

            if ($load->estaAnulada()) {
                throw ValidationException::withMessages(['tipo' => 'La carga está anulada y no admite nuevos ajustes.']);
            }

            Producto::query()->whereKey($load->producto_id)->lockForUpdate()->firstOrFail();
            AjusteProveedor::query()->where('carga_id', $load->getKey())->orderBy('id')->lockForUpdate()->get(['id']);
            $balance = DB::table('vw_saldos_carga_proveedor')->where('carga_id', $load->getKey())->firstOrFail();
            $balanceCents = $this->moneyToCents($balance->saldo_pendiente);

            if ($balanceCents <= 0) {
                throw ValidationException::withMessages(['tipo' => 'La carga ya no tiene saldo pendiente para ajustar.']);
            }

            $inventoryAdjustment = $validated['tipo'] === 'DEVOLUCION'
                ? $this->createReturnInventoryAdjustment($load, $validated, $user)
                : null;
            $amountCents = $validated['tipo'] === 'DESCUENTO'
                ? $this->discountAmountCents($validated['nuevo_saldo'], $balanceCents)
                : $this->returnAmountCents($validated['peso_kg'], $load->costo_kg);

            if ($amountCents > $balanceCents) {
                $field = $validated['tipo'] === 'DESCUENTO' ? 'nuevo_saldo' : 'peso_kg';

                throw ValidationException::withMessages([
                    $field => 'El valor del ajuste no puede superar el saldo pendiente de la carga.',
                ]);
            }

            $adjustment = AjusteProveedor::query()->create([
                'numero_ajuste' => 'TMP-'.Str::ulid(),
                'carga_id' => $load->getKey(),
                'pago_proveedor_id' => null,
                'ajuste_mercaderia_id' => $inventoryAdjustment?->getKey(),
                'tipo' => $validated['tipo'],
                'monto' => $amountCents / 100,
                'motivo' => $validated['motivo'],
                'usuario_id' => $user->getKey(),
                'fecha_ajuste' => now(),
            ]);
            $adjustment->update(['numero_ajuste' => sprintf('AJP-%s-%06d', now()->format('Ymd'), $adjustment->getKey())]);

            return $adjustment;
        }, 3);

        return to_route('cargas-proveedor.show', $cargaProveedor)
            ->with('status', "Ajuste {$adjustment->numero_ajuste} registrado correctamente.");
    }

    /** @param array<string, mixed> $validated */
    private function createReturnInventoryAdjustment(CargaProveedor $load, array $validated, Usuario $user): AjusteMercaderia
    {
        $available = $this->availableReturn($load);
        $stock = DB::table('vw_saldo_mercaderia_actual')->where('producto_id', $load->producto_id)->first();

        if ((int) $validated['cantidad_pollos'] > (int) $available->cantidad_pollos
            || (int) $validated['cantidad_pollos'] > (int) ($stock?->pollos_disponibles ?? 0)) {
            throw ValidationException::withMessages(['cantidad_pollos' => 'La devolución supera las aves disponibles de esta carga o del inventario.']);
        }

        if ($this->kilogramsToGrams($validated['peso_kg']) > $this->kilogramsToGrams($available->peso_kg)
            || $this->kilogramsToGrams($validated['peso_kg']) > $this->kilogramsToGrams($stock?->kg_disponibles ?? 0)) {
            throw ValidationException::withMessages(['peso_kg' => 'La devolución supera el peso disponible de esta carga o del inventario.']);
        }

        $type = TipoAjusteMercaderia::query()
            ->where('codigo', 'DEVOLUCION_PROVEEDOR')
            ->where('activo', true)
            ->lockForUpdate()
            ->firstOrFail();
        $inventoryAdjustment = AjusteMercaderia::query()->create([
            'numero_ajuste' => 'TMP-'.Str::ulid(),
            'producto_id' => $load->producto_id,
            'tipo_ajuste_id' => $type->getKey(),
            'cantidad_pollos' => $validated['cantidad_pollos'],
            'peso_kg' => $validated['peso_kg'],
            'motivo' => $validated['motivo'],
            'usuario_id' => $user->getKey(),
            'fecha_ajuste' => now(),
        ]);
        $inventoryAdjustment->update([
            'numero_ajuste' => sprintf('AJU-%s-%06d', now()->format('Ymd'), $inventoryAdjustment->getKey()),
        ]);

        return $inventoryAdjustment;
    }

    private function availableReturn(CargaProveedor $load): object
    {
        $summary = DB::table('vw_resumen_carga')->where('carga_id', $load->getKey())->firstOrFail();
        $returned = DB::table('ajustes_proveedor as ap')
            ->join('ajustes_mercaderia as am', 'am.id', '=', 'ap.ajuste_mercaderia_id')
            ->where('ap.carga_id', $load->getKey())
            ->where('ap.tipo', 'DEVOLUCION')
            ->whereNull('ap.anulado_at')
            ->whereNull('am.anulado_at')
            ->selectRaw('COALESCE(SUM(am.cantidad_pollos),0) AS cantidad_pollos, COALESCE(SUM(am.peso_kg),0) AS peso_kg')
            ->first();

        return (object) [
            'cantidad_pollos' => max(0, (int) $summary->cantidad_pollos - (int) ($returned?->cantidad_pollos ?? 0)),
            'peso_kg' => max(0, (float) $summary->peso_neto_kg - (float) ($returned?->peso_kg ?? 0)),
        ];
    }

    private function ensureLoadCanBeAdjusted(CargaProveedor $load): void
    {
        abort_if($load->estaAnulada(), 409, 'No puedes ajustar una carga anulada.');
        abort_if(
            $this->moneyToCents(DB::table('vw_saldos_carga_proveedor')->where('carga_id', $load->getKey())->value('saldo_pendiente')) <= 0,
            409,
            'La carga no tiene saldo pendiente para ajustar.',
        );
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function discountAmountCents(mixed $newBalance, int $currentBalanceCents): int
    {
        $newBalanceCents = $this->moneyToCents($newBalance);

        if ($newBalanceCents >= $currentBalanceCents) {
            throw ValidationException::withMessages([
                'nuevo_saldo' => 'El nuevo saldo debe ser menor que el saldo pendiente actual.',
            ]);
        }

        return $currentBalanceCents - $newBalanceCents;
    }

    private function returnAmountCents(mixed $kilograms, mixed $unitCost): int
    {
        $amountCents = (int) round(
            $this->kilogramsToGrams($kilograms)
            * $this->priceToTenThousandths($unitCost)
            / 100000,
        );

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'peso_kg' => 'El peso devuelto no genera un valor válido para esta carga.',
            ]);
        }

        return $amountCents;
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }

    private function priceToTenThousandths(mixed $price): int
    {
        return (int) round((float) $price * 10000);
    }
}
