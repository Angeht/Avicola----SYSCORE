<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE cargas_proveedor (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_carga        VARCHAR(30) NOT NULL UNIQUE,
    proveedor_id        BIGINT UNSIGNED NOT NULL,
    producto_id         BIGINT UNSIGNED NOT NULL,
    fecha_carga         DATE NOT NULL,
    costo_total         DECIMAL(14,2) NOT NULL,
    recibido_por        BIGINT UNSIGNED NOT NULL,
    observacion         VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carga_proveedor
        FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_carga_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_carga_usuario
        FOREIGN KEY (recibido_por) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_costo_carga CHECK (costo_total > 0),
    INDEX idx_carga_fecha (fecha_carga),
    INDEX idx_carga_proveedor_fecha (proveedor_id, fecha_carga)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_proveedor');
    }
};
