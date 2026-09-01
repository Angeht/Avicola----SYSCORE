<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE ventas (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_venta        VARCHAR(30) NOT NULL UNIQUE,
    cliente_id          BIGINT UNSIGNED NULL,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    sesion_caja_id      BIGINT UNSIGNED NULL,
    fecha_venta         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulada_por         BIGINT UNSIGNED NULL,
    anulada_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    observacion         VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_venta_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_caja
        FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_anulada_por
        FOREIGN KEY (anulada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_venta_anulacion CHECK (
        (anulada_at IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulada_at IS NOT NULL AND anulada_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_venta_fecha (fecha_venta),
    INDEX idx_venta_cliente_fecha (cliente_id, fecha_venta),
    INDEX idx_venta_usuario_fecha (usuario_id, fecha_venta)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
