<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE configuracion_empresa (
    id                  TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    razon_social        VARCHAR(150) NOT NULL,
    nombre_comercial    VARCHAR(150) NULL,
    tipo_documento_id   SMALLINT UNSIGNED NULL,
    nro_documento       VARCHAR(20) NULL,
    direccion           VARCHAR(255) NULL,
    telefono            VARCHAR(30) NULL,
    mensaje_ticket      VARCHAR(255) NULL,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_empresa_unica CHECK (id = 1),
    CONSTRAINT chk_empresa_documento CHECK (
        (tipo_documento_id IS NULL AND nro_documento IS NULL)
        OR
        (tipo_documento_id IS NOT NULL AND nro_documento IS NOT NULL)
    ),
    CONSTRAINT fk_empresa_tipo_documento
        FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_empresa');
    }
};
