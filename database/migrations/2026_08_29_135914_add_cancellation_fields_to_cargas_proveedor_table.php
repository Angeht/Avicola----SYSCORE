<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->foreignId('anulada_por')
                ->nullable()
                ->after('observacion')
                ->constrained('usuarios')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->dateTime('anulada_at')->nullable()->after('anulada_por')->index();
            $table->string('motivo_anulacion', 255)->nullable()->after('anulada_at');
        });

        DB::table('permisos')->updateOrInsert(
            ['codigo' => 'CARGAS_ANULAR'],
            [
                'nombre' => 'Anular cargas de proveedor',
                'descripcion' => 'Anular recepciones incorrectas conservando responsable, fecha y motivo.',
            ],
        );

        $this->createCancellationAwareViews();
        $this->createCancellationTriggers();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_carga_proveedor_validar_anulacion');
        $this->restorePaymentTrigger();
        $this->restoreOriginalViews();

        DB::table('permisos')->where('codigo', 'CARGAS_ANULAR')->delete();

        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->dropForeign(['anulada_por']);
            $table->dropIndex(['anulada_at']);
            $table->dropColumn(['anulada_por', 'anulada_at', 'motivo_anulacion']);
        });
    }

    private function createCancellationAwareViews(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_resumen_carga AS
SELECT
    cp.id AS carga_id,
    cp.numero_carga,
    cp.proveedor_id,
    cp.producto_id,
    cp.fecha_carga,
    cp.costo_total,
    cp.anulada_por,
    cp.anulada_at,
    cp.motivo_anulacion,
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
    cp.producto_id, cp.fecha_carga, cp.costo_total,
    cp.anulada_por, cp.anulada_at, cp.motivo_anulacion;
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
    CASE
        WHEN rc.anulada_at IS NOT NULL THEN 0.00
        ELSE ROUND(COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2)
    END AS total_pagado,
    CASE
        WHEN rc.anulada_at IS NOT NULL THEN 0.00
        ELSE ROUND(rc.costo_total - COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2)
    END AS saldo_pendiente,
    CASE
        WHEN rc.anulada_at IS NOT NULL THEN 'ANULADA'
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) >= rc.costo_total THEN 'SALDADA'
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) > 0 THEN 'PARCIAL'
        WHEN rc.fecha_carga < CURDATE() THEN 'PAGO_ATRASADO'
        ELSE 'PENDIENTE_HOY'
    END AS estado_pago,
    CASE
        WHEN rc.anulada_at IS NULL
         AND COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) < rc.costo_total THEN 1
        ELSE 0
    END AS requiere_alerta
FROM vw_resumen_carga rc
LEFT JOIN pagos_proveedor pp ON pp.carga_id = rc.carga_id
GROUP BY
    rc.carga_id, rc.numero_carga, rc.proveedor_id,
    rc.fecha_carga, rc.peso_neto_kg, rc.costo_total, rc.anulada_at;
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
WHERE cp.anulada_at IS NULL
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
    }

    private function createCancellationTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pago_proveedor_validar_insert');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pago_proveedor_validar_insert
BEFORE INSERT ON pagos_proveedor
FOR EACH ROW
BEGIN
    DECLARE v_costo_total DECIMAL(14,2);
    DECLARE v_pagado DECIMAL(14,2);
    DECLARE v_fecha_carga DATE;
    DECLARE v_carga_anulada DATETIME;
    DECLARE v_usuario BIGINT UNSIGNED;
    DECLARE v_fecha_caja DATE;
    DECLARE v_cierre DATETIME;

    SELECT costo_total, fecha_carga, anulada_at
      INTO v_costo_total, v_fecha_carga, v_carga_anulada
    FROM cargas_proveedor
    WHERE id = NEW.carga_id;

    IF v_costo_total IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La carga indicada no existe.';
    END IF;

    IF v_carga_anulada IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede pagar una carga anulada.';
    END IF;

    IF DATE(NEW.pagado_at) < v_fecha_carga THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede registrar un pago anterior a la fecha de la carga.';
    END IF;

    SELECT COALESCE(SUM(monto),0)
      INTO v_pagado
    FROM pagos_proveedor
    WHERE carga_id = NEW.carga_id
      AND anulada_at IS NULL;

    IF (v_pagado + NEW.monto) > v_costo_total THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El pago supera el saldo pendiente de la carga.';
    END IF;

    IF NEW.sesion_caja_id IS NOT NULL THEN
        SELECT usuario_id, fecha_operacion, cierre_at
          INTO v_usuario, v_fecha_caja, v_cierre
        FROM sesiones_caja
        WHERE id = NEW.sesion_caja_id;

        IF v_usuario IS NULL
           OR v_usuario <> NEW.pagado_por
           OR v_fecha_caja <> DATE(NEW.pagado_at)
           OR v_cierre IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La sesión de caja del pago a proveedor no es válida.';
        END IF;
    END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_carga_proveedor_validar_anulacion');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_carga_proveedor_validar_anulacion
BEFORE UPDATE ON cargas_proveedor
FOR EACH ROW
BEGIN
    DECLARE v_pagos_vigentes INT DEFAULT 0;

    IF OLD.anulada_at IS NULL AND NEW.anulada_at IS NOT NULL THEN
        SELECT COUNT(*)
          INTO v_pagos_vigentes
        FROM pagos_proveedor
        WHERE carga_id = OLD.id
          AND anulada_at IS NULL;

        IF v_pagos_vigentes > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Anule primero los pagos vigentes de la carga.';
        END IF;

        IF NEW.anulada_por IS NULL OR NEW.motivo_anulacion IS NULL OR CHAR_LENGTH(TRIM(NEW.motivo_anulacion)) < 10 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La anulación requiere responsable, fecha y un motivo válido.';
        END IF;
    END IF;

    IF OLD.anulada_at IS NOT NULL AND (
        NOT (OLD.anulada_por <=> NEW.anulada_por)
        OR NOT (OLD.anulada_at <=> NEW.anulada_at)
        OR NOT (OLD.motivo_anulacion <=> NEW.motivo_anulacion)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La anulación de una carga no puede modificarse ni revertirse.';
    END IF;
END
SQL);
    }

    private function restorePaymentTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_pago_proveedor_validar_insert');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pago_proveedor_validar_insert
BEFORE INSERT ON pagos_proveedor
FOR EACH ROW
BEGIN
    DECLARE v_costo_total DECIMAL(14,2);
    DECLARE v_pagado DECIMAL(14,2);
    DECLARE v_fecha_carga DATE;
    DECLARE v_usuario BIGINT UNSIGNED;
    DECLARE v_fecha_caja DATE;
    DECLARE v_cierre DATETIME;

    SELECT costo_total, fecha_carga
      INTO v_costo_total, v_fecha_carga
    FROM cargas_proveedor
    WHERE id = NEW.carga_id;

    IF v_costo_total IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La carga indicada no existe.';
    END IF;

    IF DATE(NEW.pagado_at) < v_fecha_carga THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede registrar un pago anterior a la fecha de la carga.';
    END IF;

    SELECT COALESCE(SUM(monto),0)
      INTO v_pagado
    FROM pagos_proveedor
    WHERE carga_id = NEW.carga_id
      AND anulada_at IS NULL;

    IF (v_pagado + NEW.monto) > v_costo_total THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El pago supera el saldo pendiente de la carga.';
    END IF;

    IF NEW.sesion_caja_id IS NOT NULL THEN
        SELECT usuario_id, fecha_operacion, cierre_at
          INTO v_usuario, v_fecha_caja, v_cierre
        FROM sesiones_caja
        WHERE id = NEW.sesion_caja_id;

        IF v_usuario IS NULL
           OR v_usuario <> NEW.pagado_por
           OR v_fecha_caja <> DATE(NEW.pagado_at)
           OR v_cierre IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La sesión de caja del pago a proveedor no es válida.';
        END IF;
    END IF;
END
SQL);
    }

    private function restoreOriginalViews(): void
    {
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
    }
};
