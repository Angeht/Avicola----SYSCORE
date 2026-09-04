<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAjusteClienteRequest;
use App\Models\AjusteCliente;
use App\Models\AjusteMercaderia;
use App\Models\Producto;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AjusteClienteController extends Controller
{
    public function create(Venta $venta): View
    {
        $this->ensureSaleCanBeAdjusted($venta);
        $venta->load('cliente:id,nombres_razon_social,nro_documento');

        return view('ajustes-clientes.create', [
            'balance' => DB::table('vw_saldos_venta')->where('venta_id', $venta->getKey())->firstOrFail(),
            'products' => $this->returnableProducts($venta),
            'sale' => $venta,
        ]);
    }

    public function store(StoreAjusteClienteRequest $request, Venta $venta): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        $adjustment = DB::transaction(function () use ($user, $validated, $venta): AjusteCliente {
            $sale = Venta::query()->whereKey($venta->getKey())->lockForUpdate()->firstOrFail();

            if ($sale->estaAnulada() || $sale->cliente_id === null) {
                throw ValidationException::withMessages(['tipo' => 'La venta ya no está disponible para registrar ajustes.']);
            }

            AjusteCliente::query()
                ->where('venta_id', $sale->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $balance = DB::table('vw_saldos_venta')->where('venta_id', $sale->getKey())->firstOrFail();
            $balanceCents = $this->moneyToCents($balance->saldo_pendiente);

            if ($balanceCents <= 0) {
                throw ValidationException::withMessages(['tipo' => 'La venta ya no tiene saldo pendiente para ajustar.']);
            }

            $return = $validated['tipo'] === 'DEVOLUCION'
                ? $this->createReturnInventoryAdjustment($sale, $validated, $user)
                : null;
            $amountCents = $validated['tipo'] === 'DESCUENTO'
                ? $this->discountAmountCents($validated['nuevo_saldo'], $balanceCents)
                : $return['amountCents'];

            if ($amountCents > $balanceCents) {
                $field = $validated['tipo'] === 'DESCUENTO' ? 'nuevo_saldo' : 'peso_kg';

                throw ValidationException::withMessages([
                    $field => 'El valor del ajuste no puede superar el saldo pendiente de la venta.',
                ]);
            }

            $adjustment = AjusteCliente::query()->create([
                'numero_ajuste' => 'TMP-'.Str::ulid(),
                'venta_id' => $sale->getKey(),
                'cobranza_id' => null,
                'ajuste_mercaderia_id' => $return === null ? null : $return['inventoryAdjustment']->getKey(),
                'tipo' => $validated['tipo'],
                'monto' => $amountCents / 100,
                'motivo' => $validated['motivo'],
                'usuario_id' => $user->getKey(),
                'fecha_ajuste' => now(),
            ]);
            $adjustment->update(['numero_ajuste' => sprintf('AJC-%s-%06d', now()->format('Ymd'), $adjustment->getKey())]);

            return $adjustment;
        }, 3);

        return to_route('ventas.show', $venta)
            ->with('status', "Ajuste {$adjustment->numero_ajuste} registrado correctamente.");
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{inventoryAdjustment: AjusteMercaderia, amountCents: int}
     */
    private function createReturnInventoryAdjustment(Venta $sale, array $validated, Usuario $user): array
    {
        $product = Producto::query()->whereKey($validated['producto_id'])->lockForUpdate()->firstOrFail();
        $available = $this->returnableProducts($sale)->firstWhere('producto_id', $product->getKey());

        if ($available === null) {
            throw ValidationException::withMessages(['producto_id' => 'El producto no pertenece a la venta seleccionada.']);
        }

        if ((int) $validated['cantidad_pollos'] > (int) $available->pollos_disponibles_devolucion) {
            throw ValidationException::withMessages(['cantidad_pollos' => 'La devolución supera las aves vendidas que aún pueden devolverse.']);
        }

        if ($this->kilogramsToGrams($validated['peso_kg']) > $this->kilogramsToGrams($available->kg_disponibles_devolucion)) {
            throw ValidationException::withMessages(['peso_kg' => 'La devolución supera el peso vendido que aún puede devolverse.']);
        }

        $amountCents = (int) round(
            $this->kilogramsToGrams($validated['peso_kg'])
            * $this->priceToTenThousandths($available->precio_promedio_kg)
            / 100000,
        );

        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['peso_kg' => 'El peso devuelto no genera un valor válido para esta venta.']);
        }

        $type = TipoAjusteMercaderia::query()
            ->where('codigo', 'DEVOLUCION_CLIENTE')
            ->where('activo', true)
            ->lockForUpdate()
            ->firstOrFail();
        $inventoryAdjustment = AjusteMercaderia::query()->create([
            'numero_ajuste' => 'TMP-'.Str::ulid(),
            'producto_id' => $product->getKey(),
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

        return [
            'inventoryAdjustment' => $inventoryAdjustment,
            'amountCents' => $amountCents,
        ];
    }

    /** @return Collection<int, object> */
    private function returnableProducts(Venta $sale): Collection
    {
        $returned = DB::table('ajustes_cliente as ac')
            ->join('ajustes_mercaderia as am', 'am.id', '=', 'ac.ajuste_mercaderia_id')
            ->where('ac.venta_id', $sale->getKey())
            ->where('ac.tipo', 'DEVOLUCION')
            ->whereNull('ac.anulado_at')
            ->whereNull('am.anulado_at')
            ->groupBy('am.producto_id')
            ->selectRaw('am.producto_id, SUM(am.cantidad_pollos) AS cantidad_devuelta, SUM(am.peso_kg) AS peso_devuelto');

        return DB::table('vw_totales_venta_detalle as d')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->leftJoinSub($returned, 'r', fn ($join) => $join->on('r.producto_id', '=', 'd.producto_id'))
            ->where('d.venta_id', $sale->getKey())
            ->groupBy('d.producto_id', 'p.nombre', 'p.modalidad_venta', 'r.cantidad_devuelta', 'r.peso_devuelto')
            ->selectRaw('d.producto_id, p.nombre AS producto, p.modalidad_venta')
            ->selectRaw('ROUND(SUM(d.total_detalle) / NULLIF(SUM(d.peso_neto_kg), 0), 4) AS precio_promedio_kg')
            ->selectRaw('GREATEST(SUM(d.cantidad_pollos) - COALESCE(r.cantidad_devuelta, 0), 0) AS pollos_disponibles_devolucion')
            ->selectRaw('ROUND(GREATEST(SUM(d.peso_neto_kg) - COALESCE(r.peso_devuelto, 0), 0), 3) AS kg_disponibles_devolucion')
            ->orderBy('p.nombre')
            ->get();
    }

    private function ensureSaleCanBeAdjusted(Venta $sale): void
    {
        abort_if($sale->estaAnulada() || $sale->cliente_id === null, 409, 'Solo puedes ajustar una venta activa asociada a un cliente.');
        abort_if(
            $this->moneyToCents(DB::table('vw_saldos_venta')->where('venta_id', $sale->getKey())->value('saldo_pendiente')) <= 0,
            409,
            'La venta no tiene saldo pendiente para ajustar.',
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

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }

    private function priceToTenThousandths(mixed $price): int
    {
        return (int) round((float) $price * 10000);
    }
}
