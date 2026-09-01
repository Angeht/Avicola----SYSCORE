<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAplicacionAbonoRequest;
use App\Models\Cobranza;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AplicacionAbonoController extends Controller
{
    public function create(Request $request, Cobranza $cobranza): View
    {
        $this->ensureAbonoCanBeApplied($cobranza);
        $cobranza->load([
            'cliente:id,nombres_razon_social,nro_documento',
            'medioPago:id,codigo,nombre,es_efectivo',
            'usuario:id,nombres,apellidos,usuario',
            'aplicaciones' => fn ($query) => $query
                ->select(['cobranza_id', 'venta_id', 'monto_aplicado', 'created_at'])
                ->with('venta:id,numero_venta,fecha_venta')
                ->orderBy('venta_id'),
        ]);
        $appliedCents = $cobranza->aplicaciones->sum(
            fn ($application): int => $this->moneyToCents($application->monto_aplicado),
        );

        return view('cobranzas.aplicar-abono', [
            'appliedAmount' => $appliedCents / 100,
            'authenticatedUser' => $this->authenticatedUser($request),
            'collection' => $cobranza,
            'pendingSales' => $this->pendingSales($cobranza),
            'remainingAmount' => max(0, $this->moneyToCents($cobranza->monto_total) - $appliedCents) / 100,
        ]);
    }

    public function store(StoreAplicacionAbonoRequest $request, Cobranza $cobranza): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $applications = collect($request->validated('aplicaciones'))
            ->sortBy('venta_id')
            ->values();

        $appliedCents = DB::transaction(function () use ($applications, $cobranza, $request, $user): int {
            $lockedCollection = Cobranza::query()
                ->whereKey($cobranza->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCollection->tipo !== 'ABONO'
                || $lockedCollection->cliente_id === null
                || $lockedCollection->estaAnulada()) {
                throw ValidationException::withMessages([
                    'aplicaciones' => 'Solo puedes distribuir un abono vigente asociado a un cliente.',
                ]);
            }

            $saleIds = $applications->pluck('venta_id')->map(fn (mixed $id): int => (int) $id);
            $lockedSales = Venta::query()
                ->whereKey($saleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'cliente_id', 'anulada_at']);

            if ($lockedSales->count() !== $saleIds->count()) {
                throw ValidationException::withMessages([
                    'aplicaciones' => 'Una de las ventas seleccionadas ya no está disponible.',
                ]);
            }

            $alreadyAppliedSaleIds = DB::table('aplicacion_cobranzas')
                ->where('cobranza_id', $lockedCollection->getKey())
                ->whereIn('venta_id', $saleIds)
                ->lockForUpdate()
                ->pluck('venta_id')
                ->map(fn (mixed $id): int => (int) $id);
            $balances = DB::table('vw_saldos_venta')
                ->whereIn('venta_id', $saleIds)
                ->get(['venta_id', 'saldo_pendiente', 'estado_pago'])
                ->keyBy('venta_id');
            $remainingCents = $this->remainingCents($lockedCollection);
            $newAppliedCents = $this->ensureApplicationsRemainValid(
                $applications,
                $lockedSales,
                $balances,
                $alreadyAppliedSaleIds,
                (int) $lockedCollection->cliente_id,
                $remainingCents,
            );

            foreach ($applications as $application) {
                $lockedCollection->aplicaciones()->create([
                    'venta_id' => $application['venta_id'],
                    'monto_aplicado' => $application['monto_aplicado'],
                ]);
            }

            $auditId = DB::table('auditorias')->insertGetId([
                'usuario_id' => $user->getKey(),
                'tabla_afectada' => 'aplicacion_cobranzas',
                'registro_id' => $lockedCollection->getKey(),
                'accion' => 'UPDATE',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
            DB::table('auditoria_detalles')->insert($applications
                ->map(fn (array $application): array => [
                    'auditoria_id' => $auditId,
                    'campo' => 'venta_'.$application['venta_id'],
                    'valor_anterior' => null,
                    'valor_nuevo' => number_format((float) $application['monto_aplicado'], 2, '.', ''),
                ])
                ->all());

            return $newAppliedCents;
        }, 3);

        return to_route('cobranzas.show', $cobranza)
            ->with('status', 'Se aplicaron S/ '.number_format($appliedCents / 100, 2, ',', '.').' del abono correctamente.');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $applications
     * @param  Collection<int, Venta>  $sales
     * @param  Collection<int|string, object>  $balances
     * @param  Collection<int, int>  $alreadyAppliedSaleIds
     */
    private function ensureApplicationsRemainValid(
        Collection $applications,
        Collection $sales,
        Collection $balances,
        Collection $alreadyAppliedSaleIds,
        int $clientId,
        int $remainingCents,
    ): int {
        $sales = $sales->keyBy('id');
        $appliedCents = 0;

        if ($remainingCents <= 0) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'Este abono ya no tiene saldo disponible para aplicar.',
            ]);
        }

        foreach ($applications as $index => $application) {
            $saleId = (int) $application['venta_id'];
            $sale = $sales->get($saleId);
            $balance = $balances->get($saleId);

            if ($sale === null || $sale->anulada_at !== null || $balance === null || $balance->estado_pago === 'ANULADA') {
                throw ValidationException::withMessages([
                    "aplicaciones.$index.venta_id" => 'La venta seleccionada no está disponible para aplicar el abono.',
                ]);
            }

            if ((int) $sale->cliente_id !== $clientId) {
                throw ValidationException::withMessages([
                    "aplicaciones.$index.venta_id" => 'La venta seleccionada no pertenece al cliente del abono.',
                ]);
            }

            if ($alreadyAppliedSaleIds->contains($saleId)) {
                throw ValidationException::withMessages([
                    "aplicaciones.$index.venta_id" => 'Este abono ya fue aplicado a la venta seleccionada.',
                ]);
            }

            $applicationCents = $this->moneyToCents($application['monto_aplicado']);

            if ($applicationCents > $this->moneyToCents($balance->saldo_pendiente)) {
                throw ValidationException::withMessages([
                    "aplicaciones.$index.monto_aplicado" => 'El monto aplicado supera el saldo pendiente de la venta.',
                ]);
            }

            $appliedCents += $applicationCents;
        }

        if ($appliedCents > $remainingCents) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'El total aplicado supera el saldo disponible del abono.',
            ]);
        }

        return $appliedCents;
    }

    private function pendingSales(Cobranza $collection): Collection
    {
        return DB::table('vw_saldos_venta as sv')
            ->join('ventas as v', 'v.id', '=', 'sv.venta_id')
            ->join('clientes as cl', 'cl.id', '=', 'sv.cliente_id')
            ->where('sv.cliente_id', $collection->cliente_id)
            ->where('sv.saldo_pendiente', '>', 0)
            ->where('sv.estado_pago', '<>', 'ANULADA')
            ->whereNotExists(function ($query) use ($collection): void {
                $query->selectRaw('1')
                    ->from('aplicacion_cobranzas as ac')
                    ->whereColumn('ac.venta_id', 'sv.venta_id')
                    ->where('ac.cobranza_id', $collection->getKey());
            })
            ->select([
                'sv.venta_id',
                'sv.numero_venta',
                'sv.cliente_id',
                'sv.fecha_venta',
                'sv.total_venta',
                'sv.saldo_pendiente',
                'cl.nombres_razon_social as cliente',
            ])
            ->orderBy('sv.fecha_venta')
            ->orderBy('sv.venta_id')
            ->get();
    }

    private function ensureAbonoCanBeApplied(Cobranza $collection): void
    {
        abort_if(
            $collection->tipo !== 'ABONO' || $collection->cliente_id === null || $collection->estaAnulada(),
            409,
            'Solo puedes distribuir un abono vigente asociado a un cliente.',
        );
        abort_if(
            $this->remainingCents($collection) <= 0,
            409,
            'Este abono ya no tiene saldo disponible para aplicar.',
        );
    }

    private function remainingCents(Cobranza $collection): int
    {
        $applied = DB::table('aplicacion_cobranzas')
            ->where('cobranza_id', $collection->getKey())
            ->sum('monto_aplicado');

        return max(0, $this->moneyToCents($collection->monto_total) - $this->moneyToCents($applied));
    }

    private function authenticatedUser(Request $request): Usuario
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        return $user;
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
