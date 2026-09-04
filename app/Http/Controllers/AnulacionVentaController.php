<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularVentaRequest;
use App\Models\AjusteCliente;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionVentaController extends Controller
{
    public function create(Request $request, Venta $venta): View
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario && $user->puedeEliminarVentas(), 403);
        abort_if($venta->estaAnulada(), 409, 'Esta venta ya fue eliminada.');
        abort_if($this->hasActiveCollections($venta), 409, 'Anula primero las cobranzas vigentes aplicadas a esta venta.');
        abort_if($this->hasActiveAdjustments($venta), 409, 'Anula primero los ajustes comerciales vigentes de esta venta.');

        $venta->load([
            'cliente:id,nombres_razon_social,nro_documento',
            'usuario:id,nombres,apellidos,usuario',
        ]);

        return view('ventas.anulacion', [
            'sale' => $venta,
            'totals' => DB::table('vw_totales_venta')->where('venta_id', $venta->getKey())->firstOrFail(),
        ]);
    }

    public function store(AnularVentaRequest $request, Venta $venta): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated, $venta): void {
            $lockedSale = Venta::query()
                ->whereKey($venta->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->estaAnulada()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Esta venta ya fue anulada.',
                ]);
            }

            $productIds = DB::table('venta_detalles as vd')
                ->join('precio_dia_versiones as pv', 'pv.id', '=', 'vd.precio_version_id')
                ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
                ->where('vd.venta_id', $lockedSale->getKey())
                ->pluck('pd.producto_id')
                ->unique()
                ->sort()
                ->values();
            Producto::query()
                ->whereKey($productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $activeCollections = DB::table('aplicacion_cobranzas as ac')
                ->join('cobranzas as c', 'c.id', '=', 'ac.cobranza_id')
                ->where('ac.venta_id', $lockedSale->getKey())
                ->whereNull('c.anulada_at')
                ->lockForUpdate()
                ->get(['ac.cobranza_id']);

            if ($activeCollections->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Anula primero las cobranzas vigentes aplicadas a esta venta.',
                ]);
            }

            $activeAdjustments = AjusteCliente::query()
                ->where('venta_id', $lockedSale->getKey())
                ->vigentes()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($activeAdjustments->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Anula primero los ajustes comerciales vigentes de esta venta.',
                ]);
            }

            $lockedSale->update([
                'anulada_por' => $user->getKey(),
                'anulada_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);
        }, 3);

        return to_route('ventas.show', $venta)
            ->with('status', "Venta {$venta->numero_venta} eliminada correctamente.");
    }

    private function hasActiveCollections(Venta $sale): bool
    {
        return DB::table('aplicacion_cobranzas as ac')
            ->join('cobranzas as c', 'c.id', '=', 'ac.cobranza_id')
            ->where('ac.venta_id', $sale->getKey())
            ->whereNull('c.anulada_at')
            ->exists();
    }

    private function hasActiveAdjustments(Venta $sale): bool
    {
        return $sale->ajustesCliente()->vigentes()->exists();
    }
}
