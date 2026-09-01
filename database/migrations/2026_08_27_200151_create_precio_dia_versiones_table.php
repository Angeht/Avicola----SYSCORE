<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE precio_dia_versiones (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    precio_dia_id   BIGINT UNSIGNED NOT NULL,
    precio_kg       DECIMAL(12,4) NOT NULL,
    vigente_desde   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por  BIGINT UNSIGNED NOT NULL,
    motivo_cambio   VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_precio_version_inicio (precio_dia_id, vigente_desde),
    CONSTRAINT fk_precio_version_dia
        FOREIGN KEY (precio_dia_id) REFERENCES precios_dia(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_precio_version_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_precio_version CHECK (precio_kg > 0),
    INDEX idx_precio_version_vigencia (precio_dia_id, vigente_desde)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('precio_dia_versiones');
    }
};
