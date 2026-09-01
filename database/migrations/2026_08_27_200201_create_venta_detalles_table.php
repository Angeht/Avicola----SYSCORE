<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE venta_detalles (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id                BIGINT UNSIGNED NOT NULL,
    precio_version_id       BIGINT UNSIGNED NOT NULL,
    precio_aplicado_kg      DECIMAL(12,4) NOT NULL,
    motivo_ajuste_precio    VARCHAR(255) NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venta_detalle_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_venta_detalle_precio
        FOREIGN KEY (precio_version_id) REFERENCES precio_dia_versiones(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_venta_detalle_precio CHECK (precio_aplicado_kg > 0),
    INDEX idx_venta_detalle_venta (venta_id),
    INDEX idx_venta_detalle_precio (precio_version_id)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
