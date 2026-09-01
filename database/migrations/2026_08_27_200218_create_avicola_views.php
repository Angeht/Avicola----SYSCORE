<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_precio_vigente AS
SELECT
    pd.id AS precio_dia_id,
    pd.producto_id,
    pd.fecha,
    pv.id AS precio_version_id,
    pv.precio_kg,
    pv.vigente_desde
FROM precios_dia pd
JOIN precio_dia_versiones pv ON pv.precio_dia_id = pd.id
WHERE pv.vigente_desde <= NOW()
  AND NOT EXISTS (
      SELECT 1
      FROM precio_dia_versiones pv2
      WHERE pv2.precio_dia_id = pv.precio_dia_id
        AND pv2.vigente_desde <= NOW()
        AND (
            pv2.vigente_desde > pv.vigente_desde
            OR (pv2.vigente_desde = pv.vigente_desde AND pv2.id > pv.id)
        )
  );
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_totales_venta_detalle AS
SELECT
    vd.id AS venta_detalle_id,
    vd.venta_id,
    pd.producto_id,
    pv.precio_kg AS precio_referencia_kg,
    vd.precio_aplicado_kg,
    CASE
        WHEN vd.precio_aplicado_kg <> pv.precio_kg THEN 1
        ELSE 0
    END AS precio_modificado,
    COALESCE(SUM(pvta.cantidad_pollos),0) AS cantidad_pollos,
    ROUND(COALESCE(SUM(
        pvta.peso_bruto_kg - (pvta.cantidad_jabas * pvta.tara_unitaria_aplicada_kg)
    ),0),3) AS peso_neto_kg,
    ROUND(
        COALESCE(SUM(
            pvta.peso_bruto_kg - (pvta.cantidad_jabas * pvta.tara_unitaria_aplicada_kg)
        ),0) * vd.precio_aplicado_kg,
        2
    ) AS total_detalle
FROM venta_detalles vd
JOIN precio_dia_versiones pv ON pv.id = vd.precio_version_id
JOIN precios_dia pd ON pd.id = pv.precio_dia_id
LEFT JOIN pesajes_venta pvta ON pvta.venta_detalle_id = vd.id
GROUP BY
    vd.id, vd.venta_id, pd.producto_id, pv.precio_kg,
    vd.precio_aplicado_kg;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_totales_venta AS
SELECT
    v.id AS venta_id,
    v.numero_venta,
    v.cliente_id,
    v.usuario_id,
    v.sesion_caja_id,
    v.fecha_venta,
    CASE WHEN v.anulada_at IS NULL THEN 'ACTIVA' ELSE 'ANULADA' END AS estado,
    COALESCE(SUM(tvd.cantidad_pollos),0) AS cantidad_pollos,
    ROUND(COALESCE(SUM(tvd.peso_neto_kg),0),3) AS peso_neto_kg,
    ROUND(COALESCE(SUM(tvd.total_detalle),0),2) AS total_venta
FROM ventas v
LEFT JOIN vw_totales_venta_detalle tvd ON tvd.venta_id = v.id
GROUP BY
    v.id, v.numero_venta, v.cliente_id, v.usuario_id,
    v.sesion_caja_id, v.fecha_venta, v.anulada_at;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_saldos_venta AS
SELECT
    tv.venta_id,
    tv.numero_venta,
    tv.cliente_id,
    tv.fecha_venta,
    tv.total_venta,
    ROUND(COALESCE(SUM(
        CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
    ),0),2) AS total_pagado,
    ROUND(
        tv.total_venta -
        COALESCE(SUM(
            CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
        ),0),
        2
    ) AS saldo_pendiente,
    CASE
        WHEN tv.estado='ANULADA' THEN 'ANULADA'
        WHEN COALESCE(SUM(
            CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
        ),0) = 0 THEN 'PENDIENTE'
        WHEN COALESCE(SUM(
            CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
        ),0) < tv.total_venta THEN 'PARCIAL'
        ELSE 'SALDADA'
    END AS estado_pago
FROM vw_totales_venta tv
LEFT JOIN aplicacion_cobranzas ac ON ac.venta_id = tv.venta_id
LEFT JOIN cobranzas c ON c.id = ac.cobranza_id
GROUP BY
    tv.venta_id, tv.numero_venta, tv.cliente_id,
    tv.fecha_venta, tv.total_venta, tv.estado;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_saldos_cliente AS
SELECT
    cl.id AS cliente_id,
    cl.nombres_razon_social AS cliente,
    ROUND(COALESCE(SUM(
        CASE WHEN sv.saldo_pendiente > 0 THEN sv.saldo_pendiente ELSE 0 END
    ),0),2) AS deuda_total,
    SUM(
        CASE WHEN sv.saldo_pendiente > 0 THEN 1 ELSE 0 END
    ) AS ventas_pendientes
FROM clientes cl
LEFT JOIN vw_saldos_venta sv
    ON sv.cliente_id = cl.id
   AND sv.estado_pago <> 'ANULADA'
GROUP BY cl.id, cl.nombres_razon_social;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_resumen_carga AS
SELECT
    cp.id AS carga_id,
    cp.numero_carga,
    cp.proveedor_id,
    cp.producto_id,
    cp.fecha_carga,
    cp.costo_total,
    COALESCE(SUM(pc.cantidad_pollos),0) AS cantidad_pollos,
    COALESCE(SUM(pc.cantidad_jabas),0) AS cantidad_jabas,
    ROUND(COALESCE(SUM(pc.peso_bruto_kg),0),3) AS peso_bruto_kg,
    ROUND(COALESCE(SUM(pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg),0),3) AS tara_total_kg,
    ROUND(COALESCE(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),0),3) AS peso_neto_kg,
    CASE
        WHEN COALESCE(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),0) > 0
        THEN ROUND(cp.costo_total / SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),4)
        ELSE 0
    END AS costo_promedio_kg
FROM cargas_proveedor cp
LEFT JOIN pesajes_carga pc ON pc.carga_id = cp.id
GROUP BY
    cp.id, cp.numero_carga, cp.proveedor_id,
    cp.producto_id, cp.fecha_carga, cp.costo_total;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_saldos_carga_proveedor AS
SELECT
    rc.carga_id,
    rc.numero_carga,
    rc.proveedor_id,
    rc.fecha_carga,
    rc.peso_neto_kg,
    rc.costo_total,
    ROUND(COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2) AS total_pagado,
    ROUND(rc.costo_total - COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2) AS saldo_pendiente,
    CASE
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) >= rc.costo_total THEN 'SALDADA'
        WHEN rc.fecha_carga < CURDATE() THEN 'PAGO_ATRASADO'
        ELSE 'PENDIENTE_HOY'
    END AS estado_pago,
    CASE
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) < rc.costo_total THEN 1
        ELSE 0
    END AS requiere_alerta
FROM vw_resumen_carga rc
LEFT JOIN pagos_proveedor pp ON pp.carga_id = rc.carga_id
GROUP BY
    rc.carga_id, rc.numero_carga, rc.proveedor_id,
    rc.fecha_carga, rc.peso_neto_kg, rc.costo_total;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_resumen_diario_ventas AS
SELECT
    DATE(tv.fecha_venta) AS fecha,
    SUM(CASE WHEN tv.estado='ACTIVA' THEN 1 ELSE 0 END) AS cantidad_ventas,
    SUM(CASE WHEN tv.estado='ACTIVA' THEN tv.cantidad_pollos ELSE 0 END) AS pollos_vendidos,
    ROUND(SUM(CASE WHEN tv.estado='ACTIVA' THEN tv.peso_neto_kg ELSE 0 END),3) AS kg_vendidos,
    ROUND(SUM(
        CASE WHEN tv.estado='ACTIVA' THEN tv.total_venta ELSE 0 END
    ),2) AS total_ventas,
    ROUND(SUM(
        CASE
            WHEN sv.saldo_pendiente > 0 AND tv.estado='ACTIVA'
            THEN sv.saldo_pendiente
            ELSE 0
        END
    ),2) AS saldo_por_cobrar
FROM vw_totales_venta tv
LEFT JOIN vw_saldos_venta sv ON sv.venta_id = tv.venta_id
GROUP BY DATE(tv.fecha_venta);
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_cobranzas_diarias AS
SELECT
    DATE(c.fecha_pago) AS fecha,
    mp.codigo AS medio_pago,
    mp.nombre AS medio_pago_nombre,
    ROUND(SUM(
        CASE WHEN c.anulada_at IS NULL THEN c.monto_total ELSE 0 END
    ),2) AS total_cobrado
FROM cobranzas c
JOIN medios_pago mp ON mp.id = c.medio_pago_id
GROUP BY DATE(c.fecha_pago), mp.codigo, mp.nombre;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_resumen_caja_usuario AS
SELECT
    sc.id AS sesion_caja_id,
    sc.usuario_id,
    sc.fecha_operacion,
    sc.apertura_at,
    sc.cierre_at,
    sc.cerrada_por,
    sc.monto_apertura,
    sc.monto_contado_efectivo,
    CASE WHEN sc.cierre_at IS NULL THEN 'ABIERTA' ELSE 'CERRADA' END AS estado,

    ROUND(COALESCE(SUM(
        CASE
            WHEN c.anulada_at IS NULL AND mp.es_efectivo=TRUE
            THEN c.monto_total ELSE 0
        END
    ),0),2) AS ingresos_efectivo,

    ROUND(COALESCE(SUM(
        CASE
            WHEN c.anulada_at IS NULL AND mp.es_efectivo=FALSE
            THEN c.monto_total ELSE 0
        END
    ),0),2) AS ingresos_otros_medios,

    (
        SELECT ROUND(COALESCE(SUM(pp.monto),0),2)
        FROM pagos_proveedor pp
        JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
        WHERE pp.sesion_caja_id = sc.id
          AND pp.anulada_at IS NULL
          AND mpp.es_efectivo = TRUE
    ) AS egresos_proveedor_efectivo,

    (
        SELECT ROUND(COALESCE(SUM(pp.monto),0),2)
        FROM pagos_proveedor pp
        JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
        WHERE pp.sesion_caja_id = sc.id
          AND pp.anulada_at IS NULL
          AND mpp.es_efectivo = FALSE
    ) AS egresos_proveedor_otros_medios,

    ROUND(
        sc.monto_apertura
        + COALESCE(SUM(
            CASE
                WHEN c.anulada_at IS NULL AND mp.es_efectivo=TRUE
                THEN c.monto_total ELSE 0
            END
        ),0)
        - (
            SELECT COALESCE(SUM(pp.monto),0)
            FROM pagos_proveedor pp
            JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
            WHERE pp.sesion_caja_id = sc.id
              AND pp.anulada_at IS NULL
              AND mpp.es_efectivo = TRUE
        ),
        2
    ) AS efectivo_esperado,

    CASE
        WHEN sc.monto_contado_efectivo IS NULL THEN NULL
        ELSE ROUND(
            sc.monto_contado_efectivo - (
                sc.monto_apertura
                + COALESCE(SUM(
                    CASE
                        WHEN c.anulada_at IS NULL AND mp.es_efectivo=TRUE
                        THEN c.monto_total ELSE 0
                    END
                ),0)
                - (
                    SELECT COALESCE(SUM(pp.monto),0)
                    FROM pagos_proveedor pp
                    JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
                    WHERE pp.sesion_caja_id = sc.id
                      AND pp.anulada_at IS NULL
                      AND mpp.es_efectivo = TRUE
                )
            ),
            2
        )
    END AS diferencia_efectivo,

    ROUND(
        COALESCE(SUM(
            CASE
                WHEN c.anulada_at IS NULL AND mp.es_efectivo=FALSE
                THEN c.monto_total ELSE 0
            END
        ),0)
        - (
            SELECT COALESCE(SUM(pp.monto),0)
            FROM pagos_proveedor pp
            JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
            WHERE pp.sesion_caja_id = sc.id
              AND pp.anulada_at IS NULL
              AND mpp.es_efectivo = FALSE
        ),
        2
    ) AS neto_otros_medios

FROM sesiones_caja sc
LEFT JOIN cobranzas c ON c.sesion_caja_id = sc.id
LEFT JOIN medios_pago mp ON mp.id = c.medio_pago_id
GROUP BY
    sc.id, sc.usuario_id, sc.fecha_operacion,
    sc.apertura_at, sc.cierre_at, sc.cerrada_por,
    sc.monto_apertura, sc.monto_contado_efectivo;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_movimientos_mercaderia AS

SELECT
    cp.fecha_carga AS fecha_movimiento,
    cp.producto_id,
    'ENTRADA_CARGA' AS tipo_movimiento,
    cp.id AS referencia_id,
    SUM(pc.cantidad_pollos) AS pollos_entrada,
    0 AS pollos_salida,
    ROUND(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),3) AS kg_entrada,
    0.000 AS kg_salida,
    SUM(pc.cantidad_pollos) AS movimiento_neto_pollos,
    ROUND(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),3) AS movimiento_neto_kg
FROM cargas_proveedor cp
JOIN pesajes_carga pc ON pc.carga_id = cp.id
GROUP BY cp.id, cp.fecha_carga, cp.producto_id

UNION ALL

SELECT
    DATE(v.fecha_venta) AS fecha_movimiento,
    pd.producto_id,
    'VENTA' AS tipo_movimiento,
    v.id AS referencia_id,
    0 AS pollos_entrada,
    SUM(pv.cantidad_pollos) AS pollos_salida,
    0.000 AS kg_entrada,
    ROUND(SUM(
        pv.peso_bruto_kg - (pv.cantidad_jabas * pv.tara_unitaria_aplicada_kg)
    ),3) AS kg_salida,
    -SUM(pv.cantidad_pollos) AS movimiento_neto_pollos,
    -ROUND(SUM(
        pv.peso_bruto_kg - (pv.cantidad_jabas * pv.tara_unitaria_aplicada_kg)
    ),3) AS movimiento_neto_kg
FROM ventas v
JOIN venta_detalles vd ON vd.venta_id = v.id
JOIN precio_dia_versiones pver ON pver.id = vd.precio_version_id
JOIN precios_dia pd ON pd.id = pver.precio_dia_id
JOIN pesajes_venta pv ON pv.venta_detalle_id = vd.id
WHERE v.anulada_at IS NULL
GROUP BY v.id, DATE(v.fecha_venta), pd.producto_id

UNION ALL

SELECT
    DATE(a.fecha_ajuste) AS fecha_movimiento,
    a.producto_id,
    ta.codigo AS tipo_movimiento,
    a.id AS referencia_id,
    CASE WHEN ta.naturaleza='ENTRADA' THEN a.cantidad_pollos ELSE 0 END,
    CASE WHEN ta.naturaleza='SALIDA' THEN a.cantidad_pollos ELSE 0 END,
    CASE WHEN ta.naturaleza='ENTRADA' THEN a.peso_kg ELSE 0.000 END,
    CASE WHEN ta.naturaleza='SALIDA' THEN a.peso_kg ELSE 0.000 END,
    CASE
        WHEN ta.naturaleza='ENTRADA' THEN a.cantidad_pollos
        ELSE -a.cantidad_pollos
    END,
    CASE
        WHEN ta.naturaleza='ENTRADA' THEN a.peso_kg
        ELSE -a.peso_kg
    END
FROM ajustes_mercaderia a
JOIN tipos_ajuste_mercaderia ta ON ta.id = a.tipo_ajuste_id
WHERE a.anulado_at IS NULL;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_movimiento_diario_mercaderia AS
SELECT
    fecha_movimiento AS fecha,
    producto_id,
    SUM(pollos_entrada) AS pollos_entrada,
    SUM(pollos_salida) AS pollos_salida,
    ROUND(SUM(kg_entrada),3) AS kg_entrada,
    ROUND(SUM(kg_salida),3) AS kg_salida,

    SUM(CASE
        WHEN tipo_movimiento='ENTRADA_CARGA'
        THEN pollos_entrada ELSE 0 END) AS pollos_recibidos_carga,

    ROUND(SUM(CASE
        WHEN tipo_movimiento='ENTRADA_CARGA'
        THEN kg_entrada ELSE 0 END),3) AS kg_recibidos_carga,

    SUM(CASE
        WHEN tipo_movimiento='VENTA'
        THEN pollos_salida ELSE 0 END) AS pollos_vendidos,

    ROUND(SUM(CASE
        WHEN tipo_movimiento='VENTA'
        THEN kg_salida ELSE 0 END),3) AS kg_vendidos,

    SUM(CASE
        WHEN tipo_movimiento='MERMA'
        THEN pollos_salida ELSE 0 END) AS pollos_merma,

    ROUND(SUM(CASE
        WHEN tipo_movimiento='MERMA'
        THEN kg_salida ELSE 0 END),3) AS kg_merma,

    SUM(movimiento_neto_pollos) AS movimiento_neto_pollos,
    ROUND(SUM(movimiento_neto_kg),3) AS movimiento_neto_kg
FROM vw_movimientos_mercaderia
GROUP BY fecha_movimiento, producto_id;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_balance_diario_mercaderia AS
SELECT
    md.fecha,
    md.producto_id,

    COALESCE(
        SUM(md.movimiento_neto_pollos) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ),0
    ) AS saldo_inicial_pollos,

    ROUND(COALESCE(
        SUM(md.movimiento_neto_kg) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ),0
    ),3) AS saldo_inicial_kg,

    md.pollos_recibidos_carga,
    md.kg_recibidos_carga,
    md.pollos_vendidos,
    md.kg_vendidos,
    md.pollos_merma,
    md.kg_merma,

    COALESCE(
        SUM(md.movimiento_neto_pollos) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ),0
    ) AS sobrante_final_pollos,

    ROUND(COALESCE(
        SUM(md.movimiento_neto_kg) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ),0
    ),3) AS sobrante_final_kg,

    CASE
        WHEN
            SUM(md.movimiento_neto_pollos) OVER (
                PARTITION BY md.producto_id
                ORDER BY md.fecha
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) < 0
            OR
            SUM(md.movimiento_neto_kg) OVER (
                PARTITION BY md.producto_id
                ORDER BY md.fecha
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) < 0
        THEN 1
        ELSE 0
    END AS requiere_revision

FROM vw_movimiento_diario_mercaderia md;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_saldo_mercaderia_actual AS
SELECT
    p.id AS producto_id,
    p.nombre AS producto,
    COALESCE(SUM(m.movimiento_neto_pollos),0) AS pollos_disponibles,
    ROUND(COALESCE(SUM(m.movimiento_neto_kg),0),3) AS kg_disponibles,
    CASE
        WHEN COALESCE(SUM(m.movimiento_neto_pollos),0) < 0
          OR COALESCE(SUM(m.movimiento_neto_kg),0) < 0
        THEN 1 ELSE 0
    END AS requiere_revision
FROM productos p
LEFT JOIN vw_movimientos_mercaderia m ON m.producto_id = p.id
WHERE p.activo = TRUE
GROUP BY p.id, p.nombre;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_conciliaciones_mercaderia AS
SELECT
    c.id AS conciliacion_id,
    c.numero_conciliacion,
    c.fecha_operacion,
    c.tipo_conciliacion,
    c.realizada_at,
    c.producto_id,
    c.usuario_id,
    c.cantidad_pollos_sistema,
    c.peso_sistema_kg,
    c.cantidad_pollos_fisico,
    c.peso_fisico_kg,
    (c.cantidad_pollos_fisico - c.cantidad_pollos_sistema) AS diferencia_pollos,
    ROUND(c.peso_fisico_kg - c.peso_sistema_kg,3) AS diferencia_peso_kg,
    CASE
        WHEN (c.cantidad_pollos_fisico - c.cantidad_pollos_sistema) = 0
         AND ABS(c.peso_fisico_kg - c.peso_sistema_kg) < 0.001
        THEN 'CUADRADO'
        ELSE 'CON_DIFERENCIA'
    END AS estado_conciliacion,
    c.observacion,
    c.created_at
FROM conciliaciones_mercaderia c;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_cobranzas_pendientes_aplicar AS
SELECT
    c.id AS cobranza_id,
    c.numero_cobranza,
    c.cliente_id,
    c.fecha_pago,
    c.monto_total,
    ROUND(COALESCE(SUM(ac.monto_aplicado),0),2) AS monto_aplicado,
    ROUND(c.monto_total - COALESCE(SUM(ac.monto_aplicado),0),2) AS monto_sin_aplicar
FROM cobranzas c
LEFT JOIN aplicacion_cobranzas ac ON ac.cobranza_id = c.id
WHERE c.anulada_at IS NULL
GROUP BY c.id, c.numero_cobranza, c.cliente_id, c.fecha_pago, c.monto_total
HAVING monto_sin_aplicar > 0;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_alertas_operativas AS
SELECT
    'PROVEEDOR_PENDIENTE' AS tipo_alerta,
    scp.carga_id AS referencia_id,
    scp.numero_carga AS referencia,
    scp.fecha_carga AS fecha,
    scp.saldo_pendiente AS monto,
    CONCAT('Carga pendiente de pago: ', scp.numero_carga) AS mensaje
FROM vw_saldos_carga_proveedor scp
WHERE scp.requiere_alerta = 1

UNION ALL

SELECT
    'SALDO_MERCADERIA_NEGATIVO',
    sma.producto_id,
    sma.producto,
    CURDATE(),
    NULL,
    CONCAT('Revisar saldo de mercadería: ', sma.producto)
FROM vw_saldo_mercaderia_actual sma
WHERE sma.requiere_revision = 1

UNION ALL

SELECT
    'COBRANZA_SIN_APLICAR',
    cpa.cobranza_id,
    cpa.numero_cobranza,
    DATE(cpa.fecha_pago),
    cpa.monto_sin_aplicar,
    CONCAT('Cobranza con monto sin aplicar: ', cpa.numero_cobranza)
FROM vw_cobranzas_pendientes_aplicar cpa

UNION ALL

SELECT
    'DEUDA_SIN_CLIENTE',
    sv.venta_id,
    sv.numero_venta,
    DATE(sv.fecha_venta),
    sv.saldo_pendiente,
    CONCAT('Venta con saldo pendiente sin cliente: ', sv.numero_venta)
FROM vw_saldos_venta sv
WHERE sv.estado_pago IN ('PENDIENTE','PARCIAL')
  AND sv.cliente_id IS NULL;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `vw_alertas_operativas`');
        DB::statement('DROP VIEW IF EXISTS `vw_cobranzas_pendientes_aplicar`');
        DB::statement('DROP VIEW IF EXISTS `vw_conciliaciones_mercaderia`');
        DB::statement('DROP VIEW IF EXISTS `vw_saldo_mercaderia_actual`');
        DB::statement('DROP VIEW IF EXISTS `vw_balance_diario_mercaderia`');
        DB::statement('DROP VIEW IF EXISTS `vw_movimiento_diario_mercaderia`');
        DB::statement('DROP VIEW IF EXISTS `vw_movimientos_mercaderia`');
        DB::statement('DROP VIEW IF EXISTS `vw_resumen_caja_usuario`');
        DB::statement('DROP VIEW IF EXISTS `vw_cobranzas_diarias`');
        DB::statement('DROP VIEW IF EXISTS `vw_resumen_diario_ventas`');
        DB::statement('DROP VIEW IF EXISTS `vw_saldos_carga_proveedor`');
        DB::statement('DROP VIEW IF EXISTS `vw_resumen_carga`');
        DB::statement('DROP VIEW IF EXISTS `vw_saldos_cliente`');
        DB::statement('DROP VIEW IF EXISTS `vw_saldos_venta`');
        DB::statement('DROP VIEW IF EXISTS `vw_totales_venta`');
        DB::statement('DROP VIEW IF EXISTS `vw_totales_venta_detalle`');
        DB::statement('DROP VIEW IF EXISTS `vw_precio_vigente`');
    }
};
