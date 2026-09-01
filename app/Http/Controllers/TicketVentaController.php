<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionEmpresa;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class TicketVentaController extends Controller
{
    public function __invoke(Venta $venta): View
    {
        $venta->load([
            'cliente:id,nombres_razon_social,nro_documento,telefono,direccion',
            'usuario:id,nombres,apellidos,usuario',
            'anuladaPor:id,nombres,apellidos,usuario',
            'detalles' => fn ($query) => $query
                ->select(['id', 'venta_id', 'precio_version_id', 'precio_aplicado_kg'])
                ->with('precioVersion.precioDia.producto:id,nombre,modalidad_venta')
                ->orderBy('id'),
        ]);

        return view('ventas.ticket', [
            'balance' => DB::table('vw_saldos_venta')
                ->where('venta_id', $venta->getKey())
                ->firstOrFail(),
            'company' => ConfiguracionEmpresa::query()
                ->with('tipoDocumento:id,codigo,nombre')
                ->firstOrNew(['id' => 1], [
                    'razon_social' => 'AVÍCOLA - CONFIGURAR',
                    'nombre_comercial' => 'AVÍCOLA - CONFIGURAR',
                ]),
            'detailTotals' => DB::table('vw_totales_venta_detalle')
                ->where('venta_id', $venta->getKey())
                ->get()
                ->keyBy('venta_detalle_id'),
            'sale' => $venta,
            'totals' => DB::table('vw_totales_venta')
                ->where('venta_id', $venta->getKey())
                ->firstOrFail(),
        ]);
    }
}
