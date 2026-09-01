<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE conciliacion_ajuste (
    conciliacion_id     BIGINT UNSIGNED NOT NULL,
    ajuste_id           BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (conciliacion_id, ajuste_id),
    UNIQUE KEY uk_conciliacion_ajuste_ajuste (ajuste_id),
    CONSTRAINT fk_conciliacion_ajuste_conciliacion
        FOREIGN KEY (conciliacion_id) REFERENCES conciliaciones_mercaderia(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_conciliacion_ajuste_ajuste
        FOREIGN KEY (ajuste_id) REFERENCES ajustes_mercaderia(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_ajuste');
    }
};
