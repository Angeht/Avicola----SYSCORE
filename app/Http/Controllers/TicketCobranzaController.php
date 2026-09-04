<?php

namespace App\Http\Controllers;

use App\Models\Cobranza;
use App\Models\ConfiguracionEmpresa;
use Illuminate\Contracts\View\View;

class TicketCobranzaController extends Controller
{
    public function __invoke(Cobranza $cobranza): View
    {
        $cobranza->load([
            'cliente:id,nombres_razon_social,nro_documento,telefono,direccion',
            'usuario:id,nombres,apellidos,usuario',
            'medioPago:id,codigo,nombre,es_efectivo',
            'anuladaPor:id,nombres,apellidos,usuario',
            'aplicaciones' => fn ($query) => $query
                ->select(['cobranza_id', 'venta_id', 'monto_aplicado'])
                ->with('venta:id,numero_venta,fecha_venta')
                ->orderBy('venta_id'),
            'ajustesRedondeo:id,cobranza_id,monto,anulado_at',
        ]);
        $appliedCents = $cobranza->aplicaciones->sum(
            fn ($application): int => $this->moneyToCents($application->monto_aplicado),
        );

        return view('cobranzas.ticket', [
            'appliedAmount' => $appliedCents / 100,
            'collection' => $cobranza,
            'company' => ConfiguracionEmpresa::query()
                ->with('tipoDocumento:id,codigo,nombre')
                ->firstOrNew(['id' => 1], [
                    'razon_social' => 'AVÍCOLA - CONFIGURAR',
                    'nombre_comercial' => 'AVÍCOLA - CONFIGURAR',
                ]),
            'roundingAmount' => $cobranza->ajustesRedondeo->whereNull('anulado_at')->sum('monto'),
            'unappliedAmount' => max(0, $this->moneyToCents($cobranza->monto_total) - $appliedCents) / 100,
        ]);
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
