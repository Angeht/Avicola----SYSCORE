<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE auditorias (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          BIGINT UNSIGNED NULL,
    tabla_afectada      VARCHAR(80) NOT NULL,
    registro_id         BIGINT UNSIGNED NULL,
    accion              ENUM('INSERT','UPDATE','DELETE','ANULAR','LOGIN','OTRO') NOT NULL,
    ip                  VARCHAR(45) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_auditoria_tabla_registro (tabla_afectada, registro_id),
    INDEX idx_auditoria_fecha (created_at)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
