<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos_beneficiado', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_proceso', 30)->unique();
            $table->foreignId('carga_proveedor_id')
                ->constrained('cargas_proveedor')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('producto_destino_id')
                ->constrained('productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('cantidad_pollos');
            $table->decimal('peso_origen_kg', 12, 3);
            $table->decimal('peso_resultante_kg', 12, 3);
            $table->dateTime('procesado_at')->index();
            $table->foreignId('procesado_por')
                ->constrained('usuarios')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('anulado_por')->nullable();
            $table->dateTime('anulado_at')->nullable()->index();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
ALTER TABLE procesos_beneficiado
ADD CONSTRAINT chk_proceso_beneficiado_cantidades
CHECK (
    cantidad_pollos > 0
    AND peso_origen_kg > 0
    AND peso_resultante_kg > 0
    AND peso_resultante_kg <= peso_origen_kg
)
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE procesos_beneficiado
ADD CONSTRAINT chk_proceso_beneficiado_anulacion
CHECK (
    (anulado_at IS NULL AND anulado_por IS NULL AND motivo_anulacion IS NULL)
    OR
    (anulado_at IS NOT NULL AND anulado_por IS NOT NULL
        AND motivo_anulacion IS NOT NULL
        AND CHAR_LENGTH(TRIM(motivo_anulacion)) >= 10)
)
SQL);

        Schema::table('procesos_beneficiado', function (Blueprint $table): void {
            $table->foreign('anulado_por')
                ->references('id')
                ->on('usuarios');
        });

        $this->createInventoryView();
        $this->createCancellationTriggers();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_proceso_beneficiado_validar_anulacion');
        $this->restoreLoadCancellationTrigger();
        $this->restoreInventoryView();
        Schema::dropIfExists('procesos_beneficiado');
    }

    private function createInventoryView(): void
    {
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
WHERE a.anulado_at IS NULL

UNION ALL

SELECT
    DATE(pb.procesado_at) AS fecha_movimiento,
    cp.producto_id,
    'SALIDA_BENEFICIADO' AS tipo_movimiento,
    pb.id AS referencia_id,
    0 AS pollos_entrada,
    pb.cantidad_pollos AS pollos_salida,
    0.000 AS kg_entrada,
    pb.peso_origen_kg AS kg_salida,
    -pb.cantidad_pollos AS movimiento_neto_pollos,
    -pb.peso_origen_kg AS movimiento_neto_kg
FROM procesos_beneficiado pb
JOIN cargas_proveedor cp ON cp.id = pb.carga_proveedor_id
WHERE pb.anulado_at IS NULL
  AND cp.anulada_at IS NULL

UNION ALL

SELECT
    DATE(pb.procesado_at) AS fecha_movimiento,
    pb.producto_destino_id AS producto_id,
    'ENTRADA_BENEFICIADO' AS tipo_movimiento,
    pb.id AS referencia_id,
    0 AS pollos_entrada,
    0 AS pollos_salida,
    pb.peso_resultante_kg AS kg_entrada,
    0.000 AS kg_salida,
    0 AS movimiento_neto_pollos,
    pb.peso_resultante_kg AS movimiento_neto_kg
FROM procesos_beneficiado pb
JOIN cargas_proveedor cp ON cp.id = pb.carga_proveedor_id
WHERE pb.anulado_at IS NULL
  AND cp.anulada_at IS NULL;
SQL);
    }

    private function restoreInventoryView(): void
    {
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
        DB::unprepared('DROP TRIGGER IF EXISTS trg_carga_proveedor_validar_anulacion');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_carga_proveedor_validar_anulacion
BEFORE UPDATE ON cargas_proveedor
FOR EACH ROW
BEGIN
    DECLARE v_pagos_vigentes INT DEFAULT 0;
    DECLARE v_beneficiados_vigentes INT DEFAULT 0;

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

        SELECT COUNT(*)
          INTO v_beneficiados_vigentes
        FROM procesos_beneficiado
        WHERE carga_proveedor_id = OLD.id
          AND anulado_at IS NULL;

        IF v_beneficiados_vigentes > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Anule primero los procesos de beneficiado vigentes de la carga.';
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

        DB::unprepared('DROP TRIGGER IF EXISTS trg_proceso_beneficiado_validar_anulacion');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_proceso_beneficiado_validar_anulacion
BEFORE UPDATE ON procesos_beneficiado
FOR EACH ROW
BEGIN
    IF OLD.anulado_at IS NULL AND NEW.anulado_at IS NOT NULL THEN
        IF NEW.anulado_por IS NULL OR NEW.motivo_anulacion IS NULL OR CHAR_LENGTH(TRIM(NEW.motivo_anulacion)) < 10 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La anulación requiere responsable, fecha y un motivo válido.';
        END IF;
    END IF;

    IF OLD.anulado_at IS NOT NULL AND (
        NOT (OLD.anulado_por <=> NEW.anulado_por)
        OR NOT (OLD.anulado_at <=> NEW.anulado_at)
        OR NOT (OLD.motivo_anulacion <=> NEW.motivo_anulacion)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La anulación de un beneficiado no puede modificarse ni revertirse.';
    END IF;
END
SQL);
    }

    private function restoreLoadCancellationTrigger(): void
    {
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
};
