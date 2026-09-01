<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE pagos_proveedor (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_pago         VARCHAR(30) NOT NULL UNIQUE,
    carga_id            BIGINT UNSIGNED NOT NULL,
    sesion_caja_id      BIGINT UNSIGNED NULL,
    medio_pago_id       SMALLINT UNSIGNED NOT NULL,
    monto               DECIMAL(14,2) NOT NULL,
    pagado_por          BIGINT UNSIGNED NOT NULL,
    pagado_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion         VARCHAR(255) NULL,
    anulada_por         BIGINT UNSIGNED NULL,
    anulada_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_proveedor_carga
        FOREIGN KEY (carga_id) REFERENCES cargas_proveedor(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_caja
        FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_medio
        FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_usuario
        FOREIGN KEY (pagado_por) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_anulada_por
        FOREIGN KEY (anulada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_pago_proveedor_monto CHECK (monto > 0),
    CONSTRAINT chk_pago_proveedor_anulacion CHECK (
        (anulada_at IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulada_at IS NOT NULL AND anulada_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_pago_proveedor_carga (carga_id),
    INDEX idx_pago_proveedor_fecha (pagado_at),
    INDEX idx_pago_proveedor_anulada (anulada_at)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_proveedor');
    }
};
