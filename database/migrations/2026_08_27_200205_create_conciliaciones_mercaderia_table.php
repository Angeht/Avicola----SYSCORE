<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE conciliaciones_mercaderia (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_conciliacion     VARCHAR(30) NOT NULL UNIQUE,
    producto_id             BIGINT UNSIGNED NOT NULL,
    fecha_operacion         DATE NOT NULL,
    tipo_conciliacion       ENUM('CIERRE','EXTRAORDINARIA') NOT NULL DEFAULT 'CIERRE',
    usuario_id              BIGINT UNSIGNED NOT NULL,
    cantidad_pollos_sistema INT NOT NULL DEFAULT 0,
    peso_sistema_kg         DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    cantidad_pollos_fisico  INT NOT NULL DEFAULT 0,
    peso_fisico_kg          DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    observacion             VARCHAR(255) NULL,
    realizada_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conciliacion_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_conciliacion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_conc_pollos_sistema CHECK (cantidad_pollos_sistema >= 0),
    CONSTRAINT chk_conc_peso_sistema CHECK (peso_sistema_kg >= 0),
    CONSTRAINT chk_conc_pollos_fisico CHECK (cantidad_pollos_fisico >= 0),
    CONSTRAINT chk_conc_peso_fisico CHECK (peso_fisico_kg >= 0),
    INDEX idx_conciliacion_producto_fecha (producto_id, fecha_operacion),
    INDEX idx_conciliacion_fecha (fecha_operacion),
    INDEX idx_conciliacion_tipo (tipo_conciliacion)
) ENGINE=InnoDB;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliaciones_mercaderia');
    }
};
