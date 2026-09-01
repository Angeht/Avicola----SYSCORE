<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE auditoria_detalles (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auditoria_id        BIGINT UNSIGNED NOT NULL,
    campo               VARCHAR(100) NOT NULL,
    valor_anterior      TEXT NULL,
    valor_nuevo         TEXT NULL,
    CONSTRAINT fk_auditoria_detalle
        FOREIGN KEY (auditoria_id) REFERENCES auditorias(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_auditoria_detalle_auditoria (auditoria_id)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_detalles');
    }
};
