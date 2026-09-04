<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularAjusteProveedorRequest;
use App\Models\AjusteMercaderia;
use App\Models\AjusteProveedor;
use App\Models\CargaProveedor;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionAjusteProveedorController extends Controller
{
    public function create(CargaProveedor $cargaProveedor, AjusteProveedor $ajusteProveedor): View
    {
        $this->ensureBelongsToLoad($cargaProveedor, $ajusteProveedor);
        abort_if($ajusteProveedor->estaAnulado(), 409, 'Este ajuste ya fue anulado.');
        $ajusteProveedor->load(['ajusteMercaderia.producto:id,nombre', 'usuario:id,nombres,apellidos,usuario']);

        return view('ajustes-proveedores.anulacion', ['adjustment' => $ajusteProveedor, 'load' => $cargaProveedor]);
    }

    public function store(
        AnularAjusteProveedorRequest $request,
        CargaProveedor $cargaProveedor,
        AjusteProveedor $ajusteProveedor,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();
        $this->ensureBelongsToLoad($cargaProveedor, $ajusteProveedor);

        DB::transaction(function () use ($ajusteProveedor, $cargaProveedor, $user, $validated): void {
            $load = CargaProveedor::query()->whereKey($cargaProveedor->getKey())->lockForUpdate()->firstOrFail();
            Producto::query()->whereKey($load->producto_id)->lockForUpdate()->firstOrFail();
            $adjustment = AjusteProveedor::query()->whereKey($ajusteProveedor->getKey())->lockForUpdate()->firstOrFail();

            if ($adjustment->estaAnulado()) {
                throw ValidationException::withMessages(['motivo_anulacion' => 'Este ajuste ya fue anulado.']);
            }

            if ($adjustment->ajuste_mercaderia_id !== null) {
                $inventoryAdjustment = AjusteMercaderia::query()
                    ->whereKey($adjustment->ajuste_mercaderia_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($inventoryAdjustment->estaAnulado()) {
                    throw ValidationException::withMessages(['motivo_anulacion' => 'El movimiento de mercadería vinculado ya fue anulado.']);
                }

                $inventoryAdjustment->update([
                    'anulado_por' => $user->getKey(),
                    'anulado_at' => now(),
                    'motivo_anulacion' => $validated['motivo_anulacion'],
                ]);
            }

            $adjustment->update([
                'anulado_por' => $user->getKey(),
                'anulado_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);
        }, 3);

        return to_route('cargas-proveedor.show', $cargaProveedor)
            ->with('status', "Ajuste {$ajusteProveedor->numero_ajuste} anulado correctamente.");
    }

    private function ensureBelongsToLoad(CargaProveedor $load, AjusteProveedor $adjustment): void
    {
        abort_unless((int) $adjustment->carga_id === (int) $load->getKey(), 404);
    }
}
