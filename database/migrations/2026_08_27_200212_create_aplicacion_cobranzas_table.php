<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE aplicacion_cobranzas (
    cobranza_id         BIGINT UNSIGNED NOT NULL,
    venta_id            BIGINT UNSIGNED NOT NULL,
    monto_aplicado      DECIMAL(14,2) NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cobranza_id, venta_id),
    CONSTRAINT fk_aplicacion_cobranza
        FOREIGN KEY (cobranza_id) REFERENCES cobranzas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_aplicacion_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_aplicacion_monto CHECK (monto_aplicado > 0),
    INDEX idx_aplicacion_venta (venta_id)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicacion_cobranzas');
    }
};
