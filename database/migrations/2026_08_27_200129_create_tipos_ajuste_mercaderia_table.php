<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE tipos_ajuste_mercaderia (
    id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(40) NOT NULL UNIQUE,
    nombre          VARCHAR(80) NOT NULL UNIQUE,
    naturaleza      ENUM('ENTRADA','SALIDA') NOT NULL,
    requiere_motivo BOOLEAN NOT NULL DEFAULT TRUE,
    activo          BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_ajuste_mercaderia');
    }
};
