<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE proveedores (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_documento_id       SMALLINT UNSIGNED NULL,
    nro_documento           VARCHAR(20) NULL,
    nombre_razon_social     VARCHAR(150) NOT NULL,
    telefono                VARCHAR(30) NULL,
    direccion               VARCHAR(255) NULL,
    activo                  BOOLEAN NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_proveedor_documento (tipo_documento_id, nro_documento),
    CONSTRAINT chk_proveedor_documento CHECK (
        (tipo_documento_id IS NULL AND nro_documento IS NULL)
        OR
        (tipo_documento_id IS NOT NULL AND nro_documento IS NOT NULL)
    ),
    CONSTRAINT fk_proveedor_tipo_documento
        FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
