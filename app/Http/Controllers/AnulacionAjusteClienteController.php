<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularAjusteClienteRequest;
use App\Models\AjusteCliente;
use App\Models\AjusteMercaderia;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionAjusteClienteController extends Controller
{
    public function create(Venta $venta, AjusteCliente $ajusteCliente): View
    {
        $this->ensureBelongsToSale($venta, $ajusteCliente);
        abort_if($ajusteCliente->estaAnulado(), 409, 'Este ajuste ya fue anulado.');
        $ajusteCliente->load(['ajusteMercaderia.producto:id,nombre', 'usuario:id,nombres,apellidos,usuario']);

        return view('ajustes-clientes.anulacion', ['adjustment' => $ajusteCliente, 'sale' => $venta]);
    }

    public function store(AnularAjusteClienteRequest $request, Venta $venta, AjusteCliente $ajusteCliente): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();
        $this->ensureBelongsToSale($venta, $ajusteCliente);

        DB::transaction(function () use ($ajusteCliente, $user, $validated, $venta): void {
            Venta::query()->whereKey($venta->getKey())->lockForUpdate()->firstOrFail();
            $adjustment = AjusteCliente::query()->whereKey($ajusteCliente->getKey())->lockForUpdate()->firstOrFail();

            if ($adjustment->estaAnulado()) {
                throw ValidationException::withMessages(['motivo_anulacion' => 'Este ajuste ya fue anulado.']);
            }

            if ($adjustment->ajuste_mercaderia_id !== null) {
                $this->cancelInventoryEntry($adjustment->ajuste_mercaderia_id, $user, $validated['motivo_anulacion']);
            }

            $adjustment->update([
                'anulado_por' => $user->getKey(),
                'anulado_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);
        }, 3);

        return to_route('ventas.show', $venta)
            ->with('status', "Ajuste {$ajusteCliente->numero_ajuste} anulado correctamente.");
    }

    private function cancelInventoryEntry(int $inventoryAdjustmentId, Usuario $user, string $reason): void
    {
        $inventoryAdjustment = AjusteMercaderia::query()->whereKey($inventoryAdjustmentId)->lockForUpdate()->firstOrFail();
        Producto::query()->whereKey($inventoryAdjustment->producto_id)->lockForUpdate()->firstOrFail();
        $stock = DB::table('vw_saldo_mercaderia_actual')->where('producto_id', $inventoryAdjustment->producto_id)->first();

        if ($inventoryAdjustment->estaAnulado()) {
            throw ValidationException::withMessages(['motivo_anulacion' => 'El movimiento de mercadería vinculado ya fue anulado.']);
        }

        if ($inventoryAdjustment->cantidad_pollos > (int) ($stock?->pollos_disponibles ?? 0)
            || $this->kilogramsToGrams($inventoryAdjustment->peso_kg) > $this->kilogramsToGrams($stock?->kg_disponibles ?? 0)) {
            throw ValidationException::withMessages([
                'motivo_anulacion' => 'No puedes anular la devolución porque esa mercadería ya fue utilizada.',
            ]);
        }

        $inventoryAdjustment->update([
            'anulado_por' => $user->getKey(),
            'anulado_at' => now(),
            'motivo_anulacion' => $reason,
        ]);
    }

    private function ensureBelongsToSale(Venta $sale, AjusteCliente $adjustment): void
    {
        abort_unless((int) $adjustment->venta_id === (int) $sale->getKey(), 404);
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
