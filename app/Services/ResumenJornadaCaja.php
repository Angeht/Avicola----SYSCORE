<?php

namespace App\Services;

use App\Models\MedioPago;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResumenJornadaCaja
{
    public function obtener(SesionCaja $sesionCaja): object
    {
        $resumen = DB::table('vw_resumen_caja_usuario')
            ->where('sesion_caja_id', $sesionCaja->getKey())
            ->firstOrFail();

        $resumen->resultado_general_sistema = round(
            (float) $resumen->efectivo_esperado + (float) $resumen->neto_otros_medios,
            2,
        );
        $resumen->resultado_general_cierre = $resumen->monto_contado_efectivo === null
            ? null
            : round((float) $resumen->monto_contado_efectivo + (float) $resumen->neto_otros_medios, 2);

        return $resumen;
    }

    public function ultimaSesionCerradaAnterior(Usuario $usuario): ?SesionCaja
    {
        return SesionCaja::query()
            ->select([
                'id',
                'usuario_id',
                'fecha_operacion',
                'apertura_at',
                'cierre_at',
                'monto_contado_efectivo',
            ])
            ->where('usuario_id', $usuario->getKey())
            ->where('fecha_operacion', '<', today())
            ->whereNotNull('cierre_at')
            ->whereNotNull('monto_contado_efectivo')
            ->orderByDesc('fecha_operacion')
            ->orderByDesc('cierre_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, object{medio_pago_id: int, codigo: string, nombre: string, es_efectivo: bool, ingresos: string, egresos: string, neto: string}>
     */
    public function desglosePorMedio(SesionCaja $sesionCaja): Collection
    {
        $ingresos = DB::table('cobranzas')
            ->select('medio_pago_id')
            ->selectRaw('ROUND(SUM(monto_total), 2) AS ingresos')
            ->where('sesion_caja_id', $sesionCaja->getKey())
            ->whereNull('anulada_at')
            ->groupBy('medio_pago_id');
        $egresos = DB::table('pagos_proveedor')
            ->select('medio_pago_id')
            ->selectRaw('ROUND(SUM(monto), 2) AS egresos')
            ->where('sesion_caja_id', $sesionCaja->getKey())
            ->whereNull('anulada_at')
            ->groupBy('medio_pago_id');

        return MedioPago::query()
            ->leftJoinSub($ingresos, 'ingresos_medio', function ($join): void {
                $join->on('ingresos_medio.medio_pago_id', '=', 'medios_pago.id');
            })
            ->leftJoinSub($egresos, 'egresos_medio', function ($join): void {
                $join->on('egresos_medio.medio_pago_id', '=', 'medios_pago.id');
            })
            ->where(function ($query): void {
                $query->where('medios_pago.activo', true)
                    ->orWhereNotNull('ingresos_medio.ingresos')
                    ->orWhereNotNull('egresos_medio.egresos');
            })
            ->select([
                'medios_pago.id as medio_pago_id',
                'medios_pago.codigo',
                'medios_pago.nombre',
                'medios_pago.es_efectivo',
            ])
            ->selectRaw('COALESCE(ingresos_medio.ingresos, 0) AS ingresos')
            ->selectRaw('COALESCE(egresos_medio.egresos, 0) AS egresos')
            ->selectRaw('ROUND(COALESCE(ingresos_medio.ingresos, 0) - COALESCE(egresos_medio.egresos, 0), 2) AS neto')
            ->orderByDesc('medios_pago.es_efectivo')
            ->orderBy('medios_pago.nombre')
            ->get();
    }
}
