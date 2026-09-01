<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE precios_dia (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id     BIGINT UNSIGNED NOT NULL,
    fecha           DATE NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_precio_dia_producto_fecha (producto_id, fecha),
    CONSTRAINT fk_precio_dia_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_dia');
    }
};
