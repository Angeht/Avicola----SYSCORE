<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE pesajes_venta (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_detalle_id    BIGINT UNSIGNED NOT NULL,
    tipo_pesaje         ENUM('CON_JABA','DIRECTO') NOT NULL,
    tipo_jaba_id        BIGINT UNSIGNED NULL,
    cantidad_jabas      INT UNSIGNED NOT NULL DEFAULT 0,
    cantidad_pollos     INT UNSIGNED NOT NULL DEFAULT 0,
    peso_bruto_kg       DECIMAL(12,3) NOT NULL,
    tara_unitaria_aplicada_kg    DECIMAL(10,3) NOT NULL DEFAULT 0,
    observacion         VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pesaje_venta_detalle
        FOREIGN KEY (venta_detalle_id) REFERENCES venta_detalles(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pesaje_venta_jaba
        FOREIGN KEY (tipo_jaba_id) REFERENCES tipos_jaba(id),
    CONSTRAINT chk_pesaje_venta_pollos CHECK (cantidad_pollos > 0),
    CONSTRAINT chk_pesaje_venta_bruto CHECK (peso_bruto_kg > 0),
    CONSTRAINT chk_pesaje_venta_tara CHECK (tara_unitaria_aplicada_kg >= 0),
    CONSTRAINT chk_pesaje_venta_neto CHECK (
        peso_bruto_kg >= (cantidad_jabas * tara_unitaria_aplicada_kg)
    ),
    CONSTRAINT chk_pesaje_venta_tipo CHECK (
        (tipo_pesaje = 'DIRECTO'
            AND cantidad_jabas = 0
            AND tipo_jaba_id IS NULL
            AND tara_unitaria_aplicada_kg = 0)
        OR
        (tipo_pesaje = 'CON_JABA'
            AND cantidad_jabas > 0
            AND tipo_jaba_id IS NOT NULL
            AND tara_unitaria_aplicada_kg > 0)
    ),
    INDEX idx_pesaje_venta_detalle (venta_detalle_id)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pesajes_venta');
    }
};
