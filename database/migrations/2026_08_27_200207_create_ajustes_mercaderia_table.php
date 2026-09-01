<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE ajustes_mercaderia (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_ajuste       VARCHAR(30) NOT NULL UNIQUE,
    producto_id         BIGINT UNSIGNED NOT NULL,
    tipo_ajuste_id      SMALLINT UNSIGNED NOT NULL,
    cantidad_pollos     INT UNSIGNED NOT NULL DEFAULT 0,
    peso_kg             DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    motivo              VARCHAR(255) NOT NULL,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    fecha_ajuste        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulado_por         BIGINT UNSIGNED NULL,
    anulado_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ajuste_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_tipo
        FOREIGN KEY (tipo_ajuste_id) REFERENCES tipos_ajuste_mercaderia(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_anulado_por
        FOREIGN KEY (anulado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_ajuste_contenido CHECK (
        cantidad_pollos > 0 OR peso_kg > 0
    ),
    CONSTRAINT chk_ajuste_anulacion CHECK (
        (anulado_at IS NULL AND anulado_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulado_at IS NOT NULL AND anulado_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_ajuste_producto_fecha (producto_id, fecha_ajuste),
    INDEX idx_ajuste_tipo_fecha (tipo_ajuste_id, fecha_ajuste)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_mercaderia');
    }
};
