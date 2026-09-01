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
            $table->decimal('costo_kg', 12, 4)->nullable()->after('fecha_carga');
        });

        DB::statement(<<<'SQL'
UPDATE cargas_proveedor cp
LEFT JOIN (
    SELECT
        carga_id,
        ROUND(SUM(peso_bruto_kg - (cantidad_jabas * tara_unitaria_aplicada_kg)), 3) AS peso_neto_kg
    FROM pesajes_carga
    GROUP BY carga_id
) resumen ON resumen.carga_id = cp.id
SET cp.costo_kg = CASE
    WHEN resumen.peso_neto_kg > 0 THEN ROUND(cp.costo_total / resumen.peso_neto_kg, 4)
    ELSE cp.costo_total
END
SQL);

        DB::statement('ALTER TABLE cargas_proveedor DROP CHECK chk_costo_carga');
        DB::statement('ALTER TABLE cargas_proveedor MODIFY costo_kg DECIMAL(12,4) NOT NULL');
        DB::statement('ALTER TABLE cargas_proveedor MODIFY costo_total DECIMAL(14,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE cargas_proveedor ADD CONSTRAINT chk_costo_kg_carga CHECK (costo_kg > 0)');
        DB::statement('ALTER TABLE cargas_proveedor ADD CONSTRAINT chk_costo_carga CHECK (costo_total >= 0)');

        $this->createCostPerKilogramViews();
    }

    public function down(): void
    {
        if (DB::table('cargas_proveedor')->where('costo_total', '<=', 0)->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan cargas pendientes de pesajes.');
        }

        $this->restoreLegacyViews();

        DB::statement('ALTER TABLE cargas_proveedor DROP CHECK chk_costo_kg_carga');
        DB::statement('ALTER TABLE cargas_proveedor DROP CHECK chk_costo_carga');
        DB::statement('ALTER TABLE cargas_proveedor MODIFY costo_total DECIMAL(14,2) NOT NULL');

        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->dropColumn('costo_kg');
        });

        DB::statement('ALTER TABLE cargas_proveedor ADD CONSTRAINT chk_costo_carga CHECK (costo_total > 0)');
    }

    private function createCostPerKilogramViews(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_resumen_carga AS
SELECT
    cp.id AS carga_id,
    cp.numero_carga,
    cp.proveedor_id,
    cp.producto_id,
    cp.fecha_carga,
    cp.costo_kg,
    cp.costo_total,
    cp.anulada_por,
    cp.anulada_at,
    cp.motivo_anulacion,
    COALESCE(SUM(pc.cantidad_pollos),0) AS cantidad_pollos,
    COALESCE(SUM(pc.cantidad_jabas),0) AS cantidad_jabas,
    ROUND(COALESCE(SUM(pc.peso_bruto_kg),0),3) AS peso_bruto_kg,
    ROUND(COALESCE(SUM(pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg),0),3) AS tara_total_kg,
    ROUND(COALESCE(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),0),3) AS peso_neto_kg,
    cp.costo_kg AS costo_promedio_kg
FROM cargas_proveedor cp
LEFT JOIN pesajes_carga pc ON pc.carga_id = cp.id
GROUP BY
    cp.id, cp.numero_carga, cp.proveedor_id,
    cp.producto_id, cp.fecha_carga, cp.costo_kg, cp.costo_total,
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
        WHEN rc.costo_total <= 0 THEN 'SIN_PESAJES'
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) >= rc.costo_total THEN 'SALDADA'
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) > 0 THEN 'PARCIAL'
        WHEN rc.fecha_carga < CURDATE() THEN 'PAGO_ATRASADO'
        ELSE 'PENDIENTE_HOY'
    END AS estado_pago,
    CASE
        WHEN rc.anulada_at IS NULL
         AND rc.costo_total > 0
         AND COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) < rc.costo_total THEN 1
        ELSE 0
    END AS requiere_alerta
FROM vw_resumen_carga rc
LEFT JOIN pagos_proveedor pp ON pp.carga_id = rc.carga_id
GROUP BY
    rc.carga_id, rc.numero_carga, rc.proveedor_id,
    rc.fecha_carga, rc.peso_neto_kg, rc.costo_total, rc.anulada_at;
SQL);
    }

    private function restoreLegacyViews(): void
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
    }
};
