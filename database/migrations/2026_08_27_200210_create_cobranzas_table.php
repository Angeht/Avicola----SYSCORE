<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE cobranzas (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_cobranza     VARCHAR(30) NOT NULL UNIQUE,
    cliente_id          BIGINT UNSIGNED NULL,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    sesion_caja_id      BIGINT UNSIGNED NULL,
    medio_pago_id       SMALLINT UNSIGNED NOT NULL,
    tipo                ENUM('PAGO_VENTA','ABONO') NOT NULL,
    monto_total         DECIMAL(14,2) NOT NULL,
    fecha_pago          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion         VARCHAR(255) NULL,
    anulada_por         BIGINT UNSIGNED NULL,
    anulada_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cobranza_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_cobranza_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cobranza_caja
        FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cobranza_medio
        FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cobranza_anulada_por
        FOREIGN KEY (anulada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_cobranza_monto CHECK (monto_total > 0),
    CONSTRAINT chk_cobranza_cliente CHECK (
        tipo <> 'ABONO' OR cliente_id IS NOT NULL
    ),
    CONSTRAINT chk_cobranza_anulacion CHECK (
        (anulada_at IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulada_at IS NOT NULL AND anulada_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_cobranza_cliente_fecha (cliente_id, fecha_pago),
    INDEX idx_cobranza_fecha (fecha_pago)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranzas');
    }
};
