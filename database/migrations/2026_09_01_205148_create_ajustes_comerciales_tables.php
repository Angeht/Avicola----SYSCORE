<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE ajustes_cliente (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_ajuste VARCHAR(30) NOT NULL UNIQUE,
    venta_id BIGINT UNSIGNED NOT NULL,
    cobranza_id BIGINT UNSIGNED NULL,
    ajuste_mercaderia_id BIGINT UNSIGNED NULL UNIQUE,
    tipo ENUM('DESCUENTO','DEVOLUCION','REDONDEO') NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    fecha_ajuste DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulado_por BIGINT UNSIGNED NULL,
    anulado_at DATETIME NULL,
    motivo_anulacion VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ajuste_cliente_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_cliente_cobranza FOREIGN KEY (cobranza_id) REFERENCES cobranzas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_cliente_mercaderia FOREIGN KEY (ajuste_mercaderia_id) REFERENCES ajustes_mercaderia(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_cliente_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_cliente_anulado_por FOREIGN KEY (anulado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_ajuste_cliente_monto CHECK (monto > 0),
    INDEX idx_ajuste_cliente_venta_fecha (venta_id, fecha_ajuste),
    INDEX idx_ajuste_cliente_cobranza (cobranza_id),
    INDEX idx_ajuste_cliente_estado_fecha (anulado_at, fecha_ajuste)
) ENGINE=InnoDB;

CREATE TABLE ajustes_proveedor (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_ajuste VARCHAR(30) NOT NULL UNIQUE,
    carga_id BIGINT UNSIGNED NOT NULL,
    pago_proveedor_id BIGINT UNSIGNED NULL,
    ajuste_mercaderia_id BIGINT UNSIGNED NULL UNIQUE,
    tipo ENUM('DESCUENTO','DEVOLUCION') NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    fecha_ajuste DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulado_por BIGINT UNSIGNED NULL,
    anulado_at DATETIME NULL,
    motivo_anulacion VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ajuste_proveedor_carga FOREIGN KEY (carga_id) REFERENCES cargas_proveedor(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_proveedor_pago FOREIGN KEY (pago_proveedor_id) REFERENCES pagos_proveedor(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_proveedor_mercaderia FOREIGN KEY (ajuste_mercaderia_id) REFERENCES ajustes_mercaderia(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_proveedor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_proveedor_anulado_por FOREIGN KEY (anulado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_ajuste_proveedor_monto CHECK (monto > 0),
    INDEX idx_ajuste_proveedor_carga_fecha (carga_id, fecha_ajuste),
    INDEX idx_ajuste_proveedor_pago (pago_proveedor_id),
    INDEX idx_ajuste_proveedor_estado_fecha (anulado_at, fecha_ajuste)
) ENGINE=InnoDB;

CREATE TRIGGER trg_ajuste_cliente_no_editar
BEFORE UPDATE ON ajustes_cliente
FOR EACH ROW
BEGIN
    IF NOT (
        (NEW.anulado_at IS NULL AND NEW.anulado_por IS NULL AND NEW.motivo_anulacion IS NULL)
        OR (NEW.anulado_at IS NOT NULL AND NEW.anulado_por IS NOT NULL AND NEW.motivo_anulacion IS NOT NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La anulación del ajuste debe registrar responsable, fecha y motivo.';
    END IF;

    IF NOT (OLD.venta_id <=> NEW.venta_id)
       OR NOT (OLD.cobranza_id <=> NEW.cobranza_id)
       OR NOT (OLD.ajuste_mercaderia_id <=> NEW.ajuste_mercaderia_id)
       OR NOT (OLD.tipo <=> NEW.tipo)
       OR NOT (OLD.monto <=> NEW.monto)
       OR NOT (OLD.usuario_id <=> NEW.usuario_id)
       OR NOT (OLD.fecha_ajuste <=> NEW.fecha_ajuste) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No edite datos financieros del ajuste; anúlelo y registre uno nuevo.';
    END IF;
END;

CREATE TRIGGER trg_ajuste_cliente_validar_insert
BEFORE INSERT ON ajustes_cliente
FOR EACH ROW
BEGIN
    IF NOT (
        (NEW.tipo = 'DEVOLUCION' AND NEW.ajuste_mercaderia_id IS NOT NULL AND NEW.cobranza_id IS NULL)
        OR (NEW.tipo = 'REDONDEO' AND NEW.ajuste_mercaderia_id IS NULL AND NEW.cobranza_id IS NOT NULL)
        OR (NEW.tipo = 'DESCUENTO' AND NEW.ajuste_mercaderia_id IS NULL AND NEW.cobranza_id IS NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El origen del ajuste de cliente no corresponde con su tipo.';
    END IF;

    IF NOT (
        (NEW.anulado_at IS NULL AND NEW.anulado_por IS NULL AND NEW.motivo_anulacion IS NULL)
        OR (NEW.anulado_at IS NOT NULL AND NEW.anulado_por IS NOT NULL AND NEW.motivo_anulacion IS NOT NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La anulación del ajuste debe registrar responsable, fecha y motivo.';
    END IF;
END;

CREATE TRIGGER trg_ajuste_proveedor_no_editar
BEFORE UPDATE ON ajustes_proveedor
FOR EACH ROW
BEGIN
    IF NOT (
        (NEW.anulado_at IS NULL AND NEW.anulado_por IS NULL AND NEW.motivo_anulacion IS NULL)
        OR (NEW.anulado_at IS NOT NULL AND NEW.anulado_por IS NOT NULL AND NEW.motivo_anulacion IS NOT NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La anulación del ajuste debe registrar responsable, fecha y motivo.';
    END IF;

    IF NOT (OLD.carga_id <=> NEW.carga_id)
       OR NOT (OLD.pago_proveedor_id <=> NEW.pago_proveedor_id)
       OR NOT (OLD.ajuste_mercaderia_id <=> NEW.ajuste_mercaderia_id)
       OR NOT (OLD.tipo <=> NEW.tipo)
       OR NOT (OLD.monto <=> NEW.monto)
       OR NOT (OLD.usuario_id <=> NEW.usuario_id)
       OR NOT (OLD.fecha_ajuste <=> NEW.fecha_ajuste) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No edite datos financieros del ajuste; anúlelo y registre uno nuevo.';
    END IF;
END;

CREATE TRIGGER trg_ajuste_proveedor_validar_insert
BEFORE INSERT ON ajustes_proveedor
FOR EACH ROW
BEGIN
    IF NOT (
        (NEW.tipo = 'DEVOLUCION' AND NEW.ajuste_mercaderia_id IS NOT NULL AND NEW.pago_proveedor_id IS NULL)
        OR (NEW.tipo = 'DESCUENTO' AND NEW.ajuste_mercaderia_id IS NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El origen del ajuste de proveedor no corresponde con su tipo.';
    END IF;

    IF NOT (
        (NEW.anulado_at IS NULL AND NEW.anulado_por IS NULL AND NEW.motivo_anulacion IS NULL)
        OR (NEW.anulado_at IS NOT NULL AND NEW.anulado_por IS NOT NULL AND NEW.motivo_anulacion IS NOT NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La anulación del ajuste debe registrar responsable, fecha y motivo.';
    END IF;
END;
SQL);

        DB::table('tipos_ajuste_mercaderia')->updateOrInsert(
            ['codigo' => 'DEVOLUCION_CLIENTE'],
            ['nombre' => 'Devolución de cliente', 'naturaleza' => 'ENTRADA', 'requiere_motivo' => true, 'activo' => true],
        );
        DB::table('tipos_ajuste_mercaderia')->updateOrInsert(
            ['codigo' => 'DEVOLUCION_PROVEEDOR'],
            ['nombre' => 'Devolución a proveedor', 'naturaleza' => 'SALIDA', 'requiere_motivo' => true, 'activo' => true],
        );

        $this->createPermission('CLIENTES_AJUSTAR', 'Registrar descuentos y devoluciones de clientes');
        $this->createPermission('PROVEEDORES_AJUSTAR', 'Registrar descuentos y devoluciones de proveedores');
        $this->grantAlongside('CLIENTES_AJUSTAR', 'COBRANZAS_REGISTRAR');
        $this->grantAlongside('PROVEEDORES_AJUSTAR', 'PROVEEDORES_PAGAR');
        $this->createAdjustedViews();
    }

    public function down(): void
    {
        $this->createOriginalViews();
        DB::unprepared('DROP TRIGGER IF EXISTS trg_ajuste_cliente_no_editar; DROP TRIGGER IF EXISTS trg_ajuste_cliente_validar_insert; DROP TRIGGER IF EXISTS trg_ajuste_proveedor_no_editar; DROP TRIGGER IF EXISTS trg_ajuste_proveedor_validar_insert;');

        $inventoryAdjustmentIds = DB::table('ajustes_cliente')
            ->whereNotNull('ajuste_mercaderia_id')
            ->pluck('ajuste_mercaderia_id')
            ->merge(DB::table('ajustes_proveedor')->whereNotNull('ajuste_mercaderia_id')->pluck('ajuste_mercaderia_id'))
            ->unique()
            ->values();

        Schema::dropIfExists('ajustes_proveedor');
        Schema::dropIfExists('ajustes_cliente');

        if ($inventoryAdjustmentIds->isNotEmpty()) {
            DB::table('ajustes_mercaderia')->whereIn('id', $inventoryAdjustmentIds)->delete();
        }

        DB::table('tipos_ajuste_mercaderia')
            ->whereIn('codigo', ['DEVOLUCION_CLIENTE', 'DEVOLUCION_PROVEEDOR'])
            ->whereNotIn('id', DB::table('ajustes_mercaderia')->select('tipo_ajuste_id'))
            ->delete();
        $permissionIds = DB::table('permisos')
            ->whereIn('codigo', ['CLIENTES_AJUSTAR', 'PROVEEDORES_AJUSTAR'])
            ->pluck('id');
        DB::table('rol_permiso')->whereIn('permiso_id', $permissionIds)->delete();
        DB::table('permisos')->whereIn('id', $permissionIds)->delete();
    }

    private function createPermission(string $code, string $name): void
    {
        DB::table('permisos')->updateOrInsert(
            ['codigo' => $code],
            ['nombre' => $name, 'descripcion' => $name.'.'],
        );
    }

    private function grantAlongside(string $newPermissionCode, string $existingPermissionCode): void
    {
        $newPermissionId = DB::table('permisos')->where('codigo', $newPermissionCode)->value('id');
        $existingPermissionId = DB::table('permisos')->where('codigo', $existingPermissionCode)->value('id');

        if ($newPermissionId === null || $existingPermissionId === null) {
            return;
        }

        $grants = DB::table('rol_permiso')
            ->where('permiso_id', $existingPermissionId)
            ->pluck('rol_id')
            ->map(fn (mixed $roleId): array => ['rol_id' => (int) $roleId, 'permiso_id' => (int) $newPermissionId])
            ->all();

        if ($grants !== []) {
            DB::table('rol_permiso')->insertOrIgnore($grants);
        }
    }

    private function createAdjustedViews(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_saldos_venta AS
SELECT tv.venta_id,tv.numero_venta,tv.cliente_id,tv.fecha_venta,tv.total_venta,
       ROUND(COALESCE(pc.total_pagado,0),2) AS total_pagado,
       ROUND(COALESCE(aj.total_ajustado,0),2) AS total_ajustado,
       ROUND(tv.total_venta-COALESCE(pc.total_pagado,0)-COALESCE(aj.total_ajustado,0),2) AS saldo_pendiente,
       CASE WHEN tv.estado='ANULADA' THEN 'ANULADA'
            WHEN COALESCE(pc.total_pagado,0)+COALESCE(aj.total_ajustado,0)=0 THEN 'PENDIENTE'
            WHEN COALESCE(pc.total_pagado,0)+COALESCE(aj.total_ajustado,0)<tv.total_venta THEN 'PARCIAL'
            ELSE 'SALDADA' END AS estado_pago
FROM vw_totales_venta tv
LEFT JOIN (
    SELECT ac.venta_id,SUM(ac.monto_aplicado) AS total_pagado
    FROM aplicacion_cobranzas ac JOIN cobranzas c ON c.id=ac.cobranza_id
    WHERE c.anulada_at IS NULL GROUP BY ac.venta_id
) pc ON pc.venta_id=tv.venta_id
LEFT JOIN (
    SELECT venta_id,SUM(monto) AS total_ajustado FROM ajustes_cliente
    WHERE anulado_at IS NULL GROUP BY venta_id
) aj ON aj.venta_id=tv.venta_id;

CREATE OR REPLACE VIEW vw_saldos_cliente AS
SELECT cl.id AS cliente_id,cl.nombres_razon_social AS cliente,
       ROUND(COALESCE(SUM(CASE WHEN sv.saldo_pendiente>0 THEN sv.saldo_pendiente ELSE 0 END),0),2) AS deuda_total,
       SUM(CASE WHEN sv.saldo_pendiente>0 THEN 1 ELSE 0 END) AS ventas_pendientes
FROM clientes cl
LEFT JOIN vw_saldos_venta sv ON sv.cliente_id=cl.id AND sv.estado_pago<>'ANULADA'
GROUP BY cl.id,cl.nombres_razon_social;

CREATE OR REPLACE VIEW vw_saldos_carga_proveedor AS
SELECT rc.carga_id,rc.numero_carga,rc.proveedor_id,rc.fecha_carga,rc.peso_neto_kg,rc.costo_total,
       CASE WHEN rc.anulada_at IS NOT NULL THEN 0.00 ELSE ROUND(COALESCE(pp.total_pagado,0),2) END AS total_pagado,
       CASE WHEN rc.anulada_at IS NOT NULL THEN 0.00 ELSE ROUND(COALESCE(ap.total_ajustado,0),2) END AS total_ajustado,
       CASE WHEN rc.anulada_at IS NOT NULL THEN 0.00 ELSE ROUND(rc.costo_total-COALESCE(pp.total_pagado,0)-COALESCE(ap.total_ajustado,0),2) END AS saldo_pendiente,
       CASE WHEN rc.anulada_at IS NOT NULL THEN 'ANULADA'
            WHEN rc.costo_total<=0 THEN 'SIN_PESAJES'
            WHEN COALESCE(pp.total_pagado,0)+COALESCE(ap.total_ajustado,0)>=rc.costo_total THEN 'SALDADA'
            WHEN COALESCE(pp.total_pagado,0)+COALESCE(ap.total_ajustado,0)>0 THEN 'PARCIAL'
            WHEN rc.fecha_carga<CURRENT_DATE THEN 'PAGO_ATRASADO' ELSE 'PENDIENTE_HOY' END AS estado_pago,
       CASE WHEN rc.anulada_at IS NULL AND rc.costo_total>0
                 AND COALESCE(pp.total_pagado,0)+COALESCE(ap.total_ajustado,0)<rc.costo_total THEN 1 ELSE 0 END AS requiere_alerta
FROM vw_resumen_carga rc
LEFT JOIN (
    SELECT carga_id,SUM(monto) AS total_pagado FROM pagos_proveedor
    WHERE anulada_at IS NULL GROUP BY carga_id
) pp ON pp.carga_id=rc.carga_id
LEFT JOIN (
    SELECT carga_id,SUM(monto) AS total_ajustado FROM ajustes_proveedor
    WHERE anulado_at IS NULL GROUP BY carga_id
) ap ON ap.carga_id=rc.carga_id;
SQL);
    }

    private function createOriginalViews(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW vw_saldos_venta AS
SELECT tv.venta_id,tv.numero_venta,tv.cliente_id,tv.fecha_venta,tv.total_venta,
       ROUND(COALESCE(SUM(CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END),0),2) AS total_pagado,
       ROUND(tv.total_venta-COALESCE(SUM(CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END),0),2) AS saldo_pendiente,
       CASE WHEN tv.estado='ANULADA' THEN 'ANULADA'
            WHEN COALESCE(SUM(CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END),0)=0 THEN 'PENDIENTE'
            WHEN COALESCE(SUM(CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END),0)<tv.total_venta THEN 'PARCIAL'
            ELSE 'SALDADA' END AS estado_pago
FROM vw_totales_venta tv
LEFT JOIN aplicacion_cobranzas ac ON ac.venta_id=tv.venta_id
LEFT JOIN cobranzas c ON c.id=ac.cobranza_id
GROUP BY tv.venta_id,tv.numero_venta,tv.cliente_id,tv.fecha_venta,tv.total_venta,tv.estado;

CREATE OR REPLACE VIEW vw_saldos_cliente AS
SELECT cl.id AS cliente_id,cl.nombres_razon_social AS cliente,
       ROUND(COALESCE(SUM(CASE WHEN sv.saldo_pendiente>0 THEN sv.saldo_pendiente ELSE 0 END),0),2) AS deuda_total,
       SUM(CASE WHEN sv.saldo_pendiente>0 THEN 1 ELSE 0 END) AS ventas_pendientes
FROM clientes cl
LEFT JOIN vw_saldos_venta sv ON sv.cliente_id=cl.id AND sv.estado_pago<>'ANULADA'
GROUP BY cl.id,cl.nombres_razon_social;

CREATE OR REPLACE VIEW vw_saldos_carga_proveedor AS
SELECT rc.carga_id,rc.numero_carga,rc.proveedor_id,rc.fecha_carga,rc.peso_neto_kg,rc.costo_total,
       CASE WHEN rc.anulada_at IS NOT NULL THEN 0.00 ELSE ROUND(COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2) END AS total_pagado,
       CASE WHEN rc.anulada_at IS NOT NULL THEN 0.00 ELSE ROUND(rc.costo_total-COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2) END AS saldo_pendiente,
       CASE WHEN rc.anulada_at IS NOT NULL THEN 'ANULADA'
            WHEN rc.costo_total<=0 THEN 'SIN_PESAJES'
            WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0)>=rc.costo_total THEN 'SALDADA'
            WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0)>0 THEN 'PARCIAL'
            WHEN rc.fecha_carga<CURRENT_DATE THEN 'PAGO_ATRASADO' ELSE 'PENDIENTE_HOY' END AS estado_pago,
       CASE WHEN rc.anulada_at IS NULL AND rc.costo_total>0
                 AND COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0)<rc.costo_total THEN 1 ELSE 0 END AS requiere_alerta
FROM vw_resumen_carga rc
LEFT JOIN pagos_proveedor pp ON pp.carga_id=rc.carga_id
GROUP BY rc.carga_id,rc.numero_carga,rc.proveedor_id,rc.fecha_carga,rc.peso_neto_kg,rc.costo_total,rc.anulada_at;
SQL);
    }
};
