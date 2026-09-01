<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE sesiones_caja (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id              BIGINT UNSIGNED NOT NULL,
    fecha_operacion         DATE NOT NULL,
    apertura_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cierre_at               DATETIME NULL,
    cerrada_por             BIGINT UNSIGNED NULL,
    monto_apertura          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    monto_contado_efectivo  DECIMAL(14,2) NULL,
    observacion_cierre      VARCHAR(255) NULL,
    CONSTRAINT fk_caja_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_caja_cerrada_por
        FOREIGN KEY (cerrada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_monto_apertura CHECK (monto_apertura >= 0),
    CONSTRAINT chk_caja_cierre_fecha CHECK (
        cierre_at IS NULL OR cierre_at >= apertura_at
    ),
    CONSTRAINT chk_caja_monto_contado CHECK (
        monto_contado_efectivo IS NULL OR monto_contado_efectivo >= 0
    ),
    CONSTRAINT chk_caja_cierre_completo CHECK (
        (cierre_at IS NULL AND cerrada_por IS NULL AND monto_contado_efectivo IS NULL)
        OR
        (cierre_at IS NOT NULL AND cerrada_por IS NOT NULL AND monto_contado_efectivo IS NOT NULL)
    ),
    INDEX idx_caja_usuario_fecha (usuario_id, fecha_operacion),
    INDEX idx_caja_cierre (cierre_at)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_caja');
    }
};
