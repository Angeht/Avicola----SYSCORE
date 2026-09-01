<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_caja_una_abierta_insert
BEFORE INSERT ON sesiones_caja
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sesiones_caja sc
        WHERE sc.usuario_id = NEW.usuario_id
          AND sc.cierre_at IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El usuario ya tiene una sesión de caja abierta.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_caja_no_reabrir_update
BEFORE UPDATE ON sesiones_caja
FOR EACH ROW
BEGIN
    IF OLD.cierre_at IS NOT NULL AND NEW.cierre_at IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Una sesión de caja cerrada no puede reabrirse.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_precio_version_fecha_insert
BEFORE INSERT ON precio_dia_versiones
FOR EACH ROW
BEGIN
    DECLARE v_fecha DATE;
    SELECT fecha INTO v_fecha
    FROM precios_dia
    WHERE id = NEW.precio_dia_id;

    IF v_fecha IS NULL OR DATE(NEW.vigente_desde) <> v_fecha THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La fecha de vigencia del precio no coincide con el día configurado.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_precio_version_no_update
BEFORE UPDATE ON precio_dia_versiones
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'No se puede editar una versión de precio. Registre una nueva versión.';
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_venta_caja_insert
BEFORE INSERT ON ventas
FOR EACH ROW
BEGIN
    DECLARE v_usuario BIGINT UNSIGNED;
    DECLARE v_fecha DATE;
    DECLARE v_cierre DATETIME;

    IF NEW.sesion_caja_id IS NOT NULL THEN
        SELECT usuario_id, fecha_operacion, cierre_at
          INTO v_usuario, v_fecha, v_cierre
        FROM sesiones_caja
        WHERE id = NEW.sesion_caja_id;

        IF v_usuario IS NULL
           OR v_usuario <> NEW.usuario_id
           OR v_fecha <> DATE(NEW.fecha_venta)
           OR v_cierre IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La sesión de caja no corresponde al usuario, fecha o está cerrada.';
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_venta_detalle_precio_insert
BEFORE INSERT ON venta_detalles
FOR EACH ROW
BEGIN
    DECLARE v_precio_ref DECIMAL(12,4);
    DECLARE v_fecha_precio DATE;
    DECLARE v_fecha_venta DATE;

    SELECT pv.precio_kg, pd.fecha
      INTO v_precio_ref, v_fecha_precio
    FROM precio_dia_versiones pv
    JOIN precios_dia pd ON pd.id = pv.precio_dia_id
    WHERE pv.id = NEW.precio_version_id;

    SELECT DATE(fecha_venta)
      INTO v_fecha_venta
    FROM ventas
    WHERE id = NEW.venta_id;

    IF v_precio_ref IS NULL OR v_fecha_venta IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Venta o versión de precio inválida.';
    END IF;

    IF v_fecha_precio <> v_fecha_venta THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La venta debe usar el precio configurado para la misma fecha.';
    END IF;

    IF NEW.precio_aplicado_kg <> v_precio_ref
       AND (NEW.motivo_ajuste_precio IS NULL
            OR TRIM(NEW.motivo_ajuste_precio) = '') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debe indicar el motivo cuando se modifica el precio de la venta.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_venta_detalle_precio_update
BEFORE UPDATE ON venta_detalles
FOR EACH ROW
BEGIN
    DECLARE v_precio_ref DECIMAL(12,4);
    DECLARE v_fecha_precio DATE;
    DECLARE v_fecha_venta DATE;

    SELECT pv.precio_kg, pd.fecha
      INTO v_precio_ref, v_fecha_precio
    FROM precio_dia_versiones pv
    JOIN precios_dia pd ON pd.id = pv.precio_dia_id
    WHERE pv.id = NEW.precio_version_id;

    SELECT DATE(fecha_venta)
      INTO v_fecha_venta
    FROM ventas
    WHERE id = NEW.venta_id;

    IF v_precio_ref IS NULL OR v_fecha_venta IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Venta o versión de precio inválida.';
    END IF;

    IF v_fecha_precio <> v_fecha_venta THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La venta debe usar el precio configurado para la misma fecha.';
    END IF;

    IF NEW.precio_aplicado_kg <> v_precio_ref
       AND (NEW.motivo_ajuste_precio IS NULL
            OR TRIM(NEW.motivo_ajuste_precio) = '') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debe indicar el motivo cuando se modifica el precio de la venta.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_cobranza_caja_insert
BEFORE INSERT ON cobranzas
FOR EACH ROW
BEGIN
    DECLARE v_usuario BIGINT UNSIGNED;
    DECLARE v_fecha DATE;
    DECLARE v_cierre DATETIME;

    IF NEW.sesion_caja_id IS NOT NULL THEN
        SELECT usuario_id, fecha_operacion, cierre_at
          INTO v_usuario, v_fecha, v_cierre
        FROM sesiones_caja
        WHERE id = NEW.sesion_caja_id;

        IF v_usuario IS NULL
           OR v_usuario <> NEW.usuario_id
           OR v_fecha <> DATE(NEW.fecha_pago)
           OR v_cierre IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La sesión de caja de la cobranza no es válida.';
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_cobranza_no_editar
BEFORE UPDATE ON cobranzas
FOR EACH ROW
BEGIN
    IF NOT (OLD.cliente_id <=> NEW.cliente_id)
       OR NOT (OLD.usuario_id <=> NEW.usuario_id)
       OR NOT (OLD.sesion_caja_id <=> NEW.sesion_caja_id)
       OR NOT (OLD.medio_pago_id <=> NEW.medio_pago_id)
       OR NOT (OLD.tipo <=> NEW.tipo)
       OR NOT (OLD.monto_total <=> NEW.monto_total)
       OR NOT (OLD.fecha_pago <=> NEW.fecha_pago) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No edite datos financieros de la cobranza; anúlela y registre una nueva.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pago_proveedor_validar_insert
BEFORE INSERT ON pagos_proveedor
FOR EACH ROW
BEGIN
    DECLARE v_costo_total DECIMAL(14,2);
    DECLARE v_pagado DECIMAL(14,2);
    DECLARE v_fecha_carga DATE;
    DECLARE v_usuario BIGINT UNSIGNED;
    DECLARE v_fecha_caja DATE;
    DECLARE v_cierre DATETIME;

    SELECT costo_total, fecha_carga
      INTO v_costo_total, v_fecha_carga
    FROM cargas_proveedor
    WHERE id = NEW.carga_id;

    IF v_costo_total IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La carga indicada no existe.';
    END IF;

    IF DATE(NEW.pagado_at) < v_fecha_carga THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede registrar un pago anterior a la fecha de la carga.';
    END IF;

    SELECT COALESCE(SUM(monto),0)
      INTO v_pagado
    FROM pagos_proveedor
    WHERE carga_id = NEW.carga_id
      AND anulada_at IS NULL;

    IF (v_pagado + NEW.monto) > v_costo_total THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El pago supera el saldo pendiente de la carga.';
    END IF;

    IF NEW.sesion_caja_id IS NOT NULL THEN
        SELECT usuario_id, fecha_operacion, cierre_at
          INTO v_usuario, v_fecha_caja, v_cierre
        FROM sesiones_caja
        WHERE id = NEW.sesion_caja_id;

        IF v_usuario IS NULL
           OR v_usuario <> NEW.pagado_por
           OR v_fecha_caja <> DATE(NEW.pagado_at)
           OR v_cierre IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La sesión de caja del pago a proveedor no es válida.';
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_pago_proveedor_no_editar
BEFORE UPDATE ON pagos_proveedor
FOR EACH ROW
BEGIN
    IF NOT (OLD.carga_id <=> NEW.carga_id)
       OR NOT (OLD.sesion_caja_id <=> NEW.sesion_caja_id)
       OR NOT (OLD.medio_pago_id <=> NEW.medio_pago_id)
       OR NOT (OLD.monto <=> NEW.monto)
       OR NOT (OLD.pagado_por <=> NEW.pagado_por)
       OR NOT (OLD.pagado_at <=> NEW.pagado_at) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No edite datos financieros del pago; anúlelo y registre uno nuevo.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_aplicacion_cobranza_validar_insert
BEFORE INSERT ON aplicacion_cobranzas
FOR EACH ROW
BEGIN
    DECLARE v_monto_cobranza DECIMAL(14,2);
    DECLARE v_ya_aplicado DECIMAL(14,2);
    DECLARE v_saldo_venta DECIMAL(14,2);
    DECLARE v_cliente_cobranza BIGINT UNSIGNED;
    DECLARE v_cliente_venta BIGINT UNSIGNED;
    DECLARE v_venta_anulada DATETIME;

    SELECT monto_total, cliente_id
      INTO v_monto_cobranza, v_cliente_cobranza
    FROM cobranzas
    WHERE id = NEW.cobranza_id
      AND anulada_at IS NULL;

    IF v_monto_cobranza IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cobranza no existe o está anulada.';
    END IF;

    SELECT cliente_id, anulada_at
      INTO v_cliente_venta, v_venta_anulada
    FROM ventas
    WHERE id = NEW.venta_id;

    IF v_venta_anulada IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede aplicar una cobranza a una venta anulada.';
    END IF;

    IF v_cliente_cobranza IS NOT NULL
       AND NOT (v_cliente_cobranza <=> v_cliente_venta) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cobranza y la venta pertenecen a clientes diferentes.';
    END IF;

    SELECT COALESCE(SUM(monto_aplicado),0)
      INTO v_ya_aplicado
    FROM aplicacion_cobranzas
    WHERE cobranza_id = NEW.cobranza_id;

    IF (v_ya_aplicado + NEW.monto_aplicado) > v_monto_cobranza THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La aplicación supera el monto total de la cobranza.';
    END IF;

    SELECT saldo_pendiente
      INTO v_saldo_venta
    FROM vw_saldos_venta
    WHERE venta_id = NEW.venta_id;

    IF v_saldo_venta IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La venta no existe.';
    END IF;

    IF NEW.monto_aplicado > v_saldo_venta THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El abono supera el saldo pendiente de la venta.';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_aplicacion_cobranza_validar_update
BEFORE UPDATE ON aplicacion_cobranzas
FOR EACH ROW
BEGIN
    DECLARE v_monto_cobranza DECIMAL(14,2);
    DECLARE v_otros_aplicados DECIMAL(14,2);
    DECLARE v_saldo_disponible DECIMAL(14,2);
    DECLARE v_cliente_cobranza BIGINT UNSIGNED;
    DECLARE v_cliente_venta BIGINT UNSIGNED;
    DECLARE v_venta_anulada DATETIME;

    SELECT monto_total, cliente_id
      INTO v_monto_cobranza, v_cliente_cobranza
    FROM cobranzas
    WHERE id = NEW.cobranza_id
      AND anulada_at IS NULL;

    IF v_monto_cobranza IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cobranza no existe o está anulada.';
    END IF;

    SELECT cliente_id, anulada_at
      INTO v_cliente_venta, v_venta_anulada
    FROM ventas
    WHERE id = NEW.venta_id;

    IF v_venta_anulada IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede aplicar una cobranza a una venta anulada.';
    END IF;

    IF v_cliente_cobranza IS NOT NULL
       AND NOT (v_cliente_cobranza <=> v_cliente_venta) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cobranza y la venta pertenecen a clientes diferentes.';
    END IF;

    SELECT COALESCE(SUM(monto_aplicado),0)
      INTO v_otros_aplicados
    FROM aplicacion_cobranzas
    WHERE cobranza_id = NEW.cobranza_id
      AND NOT (cobranza_id = OLD.cobranza_id AND venta_id = OLD.venta_id);

    IF (v_otros_aplicados + NEW.monto_aplicado) > v_monto_cobranza THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La aplicación supera el monto total de la cobranza.';
    END IF;

    SELECT saldo_pendiente
      INTO v_saldo_disponible
    FROM vw_saldos_venta
    WHERE venta_id = NEW.venta_id;

    IF v_saldo_disponible IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La venta no existe.';
    END IF;

    IF NEW.venta_id = OLD.venta_id THEN
        SET v_saldo_disponible = v_saldo_disponible + OLD.monto_aplicado;
    END IF;

    IF NEW.monto_aplicado > v_saldo_disponible THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El abono supera el saldo pendiente de la venta.';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS `trg_aplicacion_cobranza_validar_update`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_aplicacion_cobranza_validar_insert`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_pago_proveedor_no_editar`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_pago_proveedor_validar_insert`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_cobranza_no_editar`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_cobranza_caja_insert`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_venta_detalle_precio_update`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_venta_detalle_precio_insert`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_venta_caja_insert`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_precio_version_no_update`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_precio_version_fecha_insert`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_caja_no_reabrir_update`');
        DB::statement('DROP TRIGGER IF EXISTS `trg_caja_una_abierta_insert`');
    }
};
