-- ============================================================
-- SISTEMA DE GESTIÓN PARA AVÍCOLA
-- MySQL 8.0+
-- MODELO NORMALIZADO HASTA TERCERA FORMA NORMAL (3FN)
--
-- Principios aplicados:
-- 1FN: atributos atómicos y sin grupos repetitivos.
-- 2FN: toda columna no clave depende de la clave completa.
-- 3FN: se eliminan dependencias transitivas y duplicidad lógica.
--
-- Flujo:
-- proveedor -> carga -> pesajes -> saldo mercadería
-- -> venta por kg -> pagos/abonos -> caja -> reportes
-- ============================================================

CREATE DATABASE IF NOT EXISTS avicola_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE avicola_db;
SET NAMES utf8mb4;
SET time_zone = '-05:00';

-- ============================================================
-- 1. CATÁLOGOS GENERALES
-- ============================================================

CREATE TABLE tipos_documento (
    id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(20) NOT NULL UNIQUE,
    nombre          VARCHAR(60) NOT NULL UNIQUE,
    longitud_maxima SMALLINT UNSIGNED NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE unidades_medida (
    id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(20) NOT NULL UNIQUE,
    nombre          VARCHAR(60) NOT NULL UNIQUE,
    simbolo         VARCHAR(15) NOT NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE medios_pago (
    id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(30) NOT NULL UNIQUE,
    nombre          VARCHAR(60) NOT NULL UNIQUE,
    es_efectivo     BOOLEAN NOT NULL DEFAULT FALSE,
    activo          BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE tipos_ajuste_mercaderia (
    id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(40) NOT NULL UNIQUE,
    nombre          VARCHAR(80) NOT NULL UNIQUE,
    naturaleza      ENUM('ENTRADA','SALIDA') NOT NULL,
    requiere_motivo BOOLEAN NOT NULL DEFAULT TRUE,
    activo          BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

-- ============================================================
-- 2. SEGURIDAD
-- ============================================================

CREATE TABLE roles (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(60) NOT NULL UNIQUE,
    descripcion     VARCHAR(255) NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permisos (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(80) NOT NULL UNIQUE,
    nombre          VARCHAR(100) NOT NULL,
    descripcion     VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE rol_permiso (
    rol_id          BIGINT UNSIGNED NOT NULL,
    permiso_id      BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rol_permiso_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rol_permiso_permiso
        FOREIGN KEY (permiso_id) REFERENCES permisos(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombres         VARCHAR(100) NOT NULL,
    apellidos       VARCHAR(100) NOT NULL,
    usuario         VARCHAR(80) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    activo          BOOLEAN NOT NULL DEFAULT TRUE,
    ultimo_acceso   DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuario_rol (
    usuario_id      BIGINT UNSIGNED NOT NULL,
    rol_id          BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, rol_id),
    CONSTRAINT fk_usuario_rol_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_rol_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. EMPRESA Y MAESTROS
-- ============================================================

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

CREATE TABLE clientes (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_documento_id       SMALLINT UNSIGNED NULL,
    nro_documento           VARCHAR(20) NULL,
    nombres_razon_social    VARCHAR(150) NOT NULL,
    telefono                VARCHAR(30) NULL,
    direccion               VARCHAR(255) NULL,
    observacion             VARCHAR(255) NULL,
    activo                  BOOLEAN NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cliente_documento (tipo_documento_id, nro_documento),
    CONSTRAINT chk_cliente_documento CHECK (
        (tipo_documento_id IS NULL AND nro_documento IS NULL)
        OR
        (tipo_documento_id IS NOT NULL AND nro_documento IS NOT NULL)
    ),
    CONSTRAINT fk_cliente_tipo_documento
        FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id)
) ENGINE=InnoDB;

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

CREATE TABLE productos (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(100) NOT NULL UNIQUE,
    descripcion         VARCHAR(255) NULL,
    unidad_medida_id    SMALLINT UNSIGNED NOT NULL,
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_producto_unidad
        FOREIGN KEY (unidad_medida_id) REFERENCES unidades_medida(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tipos_jaba (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(80) NOT NULL UNIQUE,
    tara_referencial_kg DECIMAL(10,3) NOT NULL,
    descripcion         VARCHAR(255) NULL,
    activo              BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_tara_jaba CHECK (tara_referencial_kg >= 0)
) ENGINE=InnoDB;

-- ============================================================
-- 4. PRECIO DEL DÍA NORMALIZADO POR VERSIONES
--
-- precios_dia identifica "producto + fecha".
-- precio_dia_versiones almacena cada cambio de precio.
-- La venta referencia la versión exacta que estaba vigente,
-- evitando guardar precio_referencia duplicado en ventas.
-- ============================================================

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

CREATE TABLE precio_dia_versiones (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    precio_dia_id   BIGINT UNSIGNED NOT NULL,
    precio_kg       DECIMAL(12,4) NOT NULL,
    vigente_desde   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por  BIGINT UNSIGNED NOT NULL,
    motivo_cambio   VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_precio_version_inicio (precio_dia_id, vigente_desde),
    CONSTRAINT fk_precio_version_dia
        FOREIGN KEY (precio_dia_id) REFERENCES precios_dia(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_precio_version_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_precio_version CHECK (precio_kg > 0),
    INDEX idx_precio_version_vigencia (precio_dia_id, vigente_desde)
) ENGINE=InnoDB;

-- ============================================================
-- 5. CAJA POR USUARIO
-- ============================================================

CREATE TABLE sesiones_caja (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id              BIGINT UNSIGNED NOT NULL,
    fecha_operacion         DATE NOT NULL,
    apertura_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cierre_at               DATETIME NULL,
    cerrada_por             BIGINT UNSIGNED NULL,
    monto_apertura          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    monto_contado_efectivo  DECIMAL(14,2) NULL,
    observacion_cierre      VARCHAR(255) NULL,
    CONSTRAINT fk_caja_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_caja_cerrada_por
        FOREIGN KEY (cerrada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_monto_apertura CHECK (monto_apertura >= 0),
    CONSTRAINT chk_caja_cierre_fecha CHECK (
        cierre_at IS NULL OR cierre_at >= apertura_at
    ),
    CONSTRAINT chk_caja_monto_contado CHECK (
        monto_contado_efectivo IS NULL OR monto_contado_efectivo >= 0
    ),
    CONSTRAINT chk_caja_cierre_completo CHECK (
        (cierre_at IS NULL AND cerrada_por IS NULL AND monto_contado_efectivo IS NULL)
        OR
        (cierre_at IS NOT NULL AND cerrada_por IS NOT NULL AND monto_contado_efectivo IS NOT NULL)
    ),
    INDEX idx_caja_usuario_fecha (usuario_id, fecha_operacion),
    INDEX idx_caja_cierre (cierre_at)
) ENGINE=InnoDB;

-- ============================================================
-- 6. CARGAS DE PROVEEDOR
-- ============================================================

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

CREATE TABLE pesajes_carga (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    carga_id            BIGINT UNSIGNED NOT NULL,
    tipo_jaba_id        BIGINT UNSIGNED NULL,
    cantidad_jabas      INT UNSIGNED NOT NULL DEFAULT 0,
    cantidad_pollos     INT UNSIGNED NOT NULL DEFAULT 0,
    peso_bruto_kg       DECIMAL(12,3) NOT NULL,
    tara_unitaria_aplicada_kg    DECIMAL(10,3) NOT NULL DEFAULT 0,
    observacion         VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pesaje_carga
        FOREIGN KEY (carga_id) REFERENCES cargas_proveedor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pesaje_carga_jaba
        FOREIGN KEY (tipo_jaba_id) REFERENCES tipos_jaba(id),
    CONSTRAINT chk_pesaje_carga_pollos CHECK (cantidad_pollos > 0),
    CONSTRAINT chk_pesaje_carga_bruto CHECK (peso_bruto_kg > 0),
    CONSTRAINT chk_pesaje_carga_tara CHECK (tara_unitaria_aplicada_kg >= 0),
    CONSTRAINT chk_pesaje_carga_neto CHECK (
        peso_bruto_kg >= (cantidad_jabas * tara_unitaria_aplicada_kg)
    ),
    CONSTRAINT chk_pesaje_carga_jaba CHECK (
        (cantidad_jabas = 0 AND tipo_jaba_id IS NULL AND tara_unitaria_aplicada_kg = 0)
        OR
        (cantidad_jabas > 0 AND tipo_jaba_id IS NOT NULL AND tara_unitaria_aplicada_kg > 0)
    )
) ENGINE=InnoDB;

CREATE TABLE pagos_proveedor (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_pago         VARCHAR(30) NOT NULL UNIQUE,
    carga_id            BIGINT UNSIGNED NOT NULL,
    sesion_caja_id      BIGINT UNSIGNED NULL,
    medio_pago_id       SMALLINT UNSIGNED NOT NULL,
    monto               DECIMAL(14,2) NOT NULL,
    pagado_por          BIGINT UNSIGNED NOT NULL,
    pagado_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion         VARCHAR(255) NULL,
    anulada_por         BIGINT UNSIGNED NULL,
    anulada_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_proveedor_carga
        FOREIGN KEY (carga_id) REFERENCES cargas_proveedor(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_caja
        FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_medio
        FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_usuario
        FOREIGN KEY (pagado_por) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor_anulada_por
        FOREIGN KEY (anulada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_pago_proveedor_monto CHECK (monto > 0),
    CONSTRAINT chk_pago_proveedor_anulacion CHECK (
        (anulada_at IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulada_at IS NOT NULL AND anulada_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_pago_proveedor_carga (carga_id),
    INDEX idx_pago_proveedor_fecha (pagado_at),
    INDEX idx_pago_proveedor_anulada (anulada_at)
) ENGINE=InnoDB;

-- ============================================================
-- 7. VENTAS NORMALIZADAS
--
-- ventas = cabecera.
-- venta_detalles = producto/precio de la operación.
-- pesajes_venta = uno o varios pesajes físicos.
--
-- producto_id NO se repite en ventas:
-- se obtiene desde precio_dia_versiones -> precios_dia -> producto.
-- ============================================================

CREATE TABLE ventas (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_venta        VARCHAR(30) NOT NULL UNIQUE,
    cliente_id          BIGINT UNSIGNED NULL,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    sesion_caja_id      BIGINT UNSIGNED NULL,
    fecha_venta         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulada_por         BIGINT UNSIGNED NULL,
    anulada_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    observacion         VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_venta_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_caja
        FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_anulada_por
        FOREIGN KEY (anulada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_venta_anulacion CHECK (
        (anulada_at IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulada_at IS NOT NULL AND anulada_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_venta_fecha (fecha_venta),
    INDEX idx_venta_cliente_fecha (cliente_id, fecha_venta),
    INDEX idx_venta_usuario_fecha (usuario_id, fecha_venta)
) ENGINE=InnoDB;

CREATE TABLE venta_detalles (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id                BIGINT UNSIGNED NOT NULL,
    precio_version_id       BIGINT UNSIGNED NOT NULL,
    precio_aplicado_kg      DECIMAL(12,4) NOT NULL,
    motivo_ajuste_precio    VARCHAR(255) NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venta_detalle_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_venta_detalle_precio
        FOREIGN KEY (precio_version_id) REFERENCES precio_dia_versiones(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_venta_detalle_precio CHECK (precio_aplicado_kg > 0),
    INDEX idx_venta_detalle_venta (venta_id),
    INDEX idx_venta_detalle_precio (precio_version_id)
) ENGINE=InnoDB;

CREATE TABLE pesajes_venta (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_detalle_id    BIGINT UNSIGNED NOT NULL,
    tipo_pesaje         ENUM('CON_JABA','DIRECTO') NOT NULL,
    tipo_jaba_id        BIGINT UNSIGNED NULL,
    cantidad_jabas      INT UNSIGNED NOT NULL DEFAULT 0,
    cantidad_pollos     INT UNSIGNED NOT NULL DEFAULT 0,
    peso_bruto_kg       DECIMAL(12,3) NOT NULL,
    tara_unitaria_aplicada_kg    DECIMAL(10,3) NOT NULL DEFAULT 0,
    observacion         VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pesaje_venta_detalle
        FOREIGN KEY (venta_detalle_id) REFERENCES venta_detalles(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pesaje_venta_jaba
        FOREIGN KEY (tipo_jaba_id) REFERENCES tipos_jaba(id),
    CONSTRAINT chk_pesaje_venta_pollos CHECK (cantidad_pollos > 0),
    CONSTRAINT chk_pesaje_venta_bruto CHECK (peso_bruto_kg > 0),
    CONSTRAINT chk_pesaje_venta_tara CHECK (tara_unitaria_aplicada_kg >= 0),
    CONSTRAINT chk_pesaje_venta_neto CHECK (
        peso_bruto_kg >= (cantidad_jabas * tara_unitaria_aplicada_kg)
    ),
    CONSTRAINT chk_pesaje_venta_tipo CHECK (
        (tipo_pesaje = 'DIRECTO'
            AND cantidad_jabas = 0
            AND tipo_jaba_id IS NULL
            AND tara_unitaria_aplicada_kg = 0)
        OR
        (tipo_pesaje = 'CON_JABA'
            AND cantidad_jabas > 0
            AND tipo_jaba_id IS NOT NULL
            AND tara_unitaria_aplicada_kg > 0)
    ),
    INDEX idx_pesaje_venta_detalle (venta_detalle_id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. CONTROL DE MERCADERÍA
-- ============================================================

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

CREATE TABLE ajustes_mercaderia (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_ajuste       VARCHAR(30) NOT NULL UNIQUE,
    producto_id         BIGINT UNSIGNED NOT NULL,
    tipo_ajuste_id      SMALLINT UNSIGNED NOT NULL,
    cantidad_pollos     INT UNSIGNED NOT NULL DEFAULT 0,
    peso_kg             DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    motivo              VARCHAR(255) NOT NULL,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    fecha_ajuste        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulado_por         BIGINT UNSIGNED NULL,
    anulado_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ajuste_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_tipo
        FOREIGN KEY (tipo_ajuste_id) REFERENCES tipos_ajuste_mercaderia(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ajuste_anulado_por
        FOREIGN KEY (anulado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_ajuste_contenido CHECK (
        cantidad_pollos > 0 OR peso_kg > 0
    ),
    CONSTRAINT chk_ajuste_anulacion CHECK (
        (anulado_at IS NULL AND anulado_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulado_at IS NOT NULL AND anulado_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_ajuste_producto_fecha (producto_id, fecha_ajuste),
    INDEX idx_ajuste_tipo_fecha (tipo_ajuste_id, fecha_ajuste)
) ENGINE=InnoDB;

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

-- ============================================================
-- 9. COBRANZAS Y ABONOS
-- ============================================================

CREATE TABLE cobranzas (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_cobranza     VARCHAR(30) NOT NULL UNIQUE,
    cliente_id          BIGINT UNSIGNED NULL,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    sesion_caja_id      BIGINT UNSIGNED NULL,
    medio_pago_id       SMALLINT UNSIGNED NOT NULL,
    tipo                ENUM('PAGO_VENTA','ABONO') NOT NULL,
    monto_total         DECIMAL(14,2) NOT NULL,
    fecha_pago          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion         VARCHAR(255) NULL,
    anulada_por         BIGINT UNSIGNED NULL,
    anulada_at          DATETIME NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cobranza_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_cobranza_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cobranza_caja
        FOREIGN KEY (sesion_caja_id) REFERENCES sesiones_caja(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cobranza_medio
        FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cobranza_anulada_por
        FOREIGN KEY (anulada_por) REFERENCES usuarios(id),
    CONSTRAINT chk_cobranza_monto CHECK (monto_total > 0),
    CONSTRAINT chk_cobranza_cliente CHECK (
        tipo <> 'ABONO' OR cliente_id IS NOT NULL
    ),
    CONSTRAINT chk_cobranza_anulacion CHECK (
        (anulada_at IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL)
        OR
        (anulada_at IS NOT NULL AND anulada_por IS NOT NULL
         AND motivo_anulacion IS NOT NULL)
    ),
    INDEX idx_cobranza_cliente_fecha (cliente_id, fecha_pago),
    INDEX idx_cobranza_fecha (fecha_pago)
) ENGINE=InnoDB;

CREATE TABLE aplicacion_cobranzas (
    cobranza_id         BIGINT UNSIGNED NOT NULL,
    venta_id            BIGINT UNSIGNED NOT NULL,
    monto_aplicado      DECIMAL(14,2) NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cobranza_id, venta_id),
    CONSTRAINT fk_aplicacion_cobranza
        FOREIGN KEY (cobranza_id) REFERENCES cobranzas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_aplicacion_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_aplicacion_monto CHECK (monto_aplicado > 0),
    INDEX idx_aplicacion_venta (venta_id)
) ENGINE=InnoDB;

-- ============================================================
-- 10. AUDITORÍA NORMALIZADA
--
-- Se reemplaza el JSON de cambios por detalle atómico
-- campo / valor anterior / valor nuevo.
-- ============================================================

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

CREATE TABLE auditoria_detalles (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auditoria_id        BIGINT UNSIGNED NOT NULL,
    campo               VARCHAR(100) NOT NULL,
    valor_anterior      TEXT NULL,
    valor_nuevo         TEXT NULL,
    CONSTRAINT fk_auditoria_detalle
        FOREIGN KEY (auditoria_id) REFERENCES auditorias(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_auditoria_detalle_auditoria (auditoria_id)
) ENGINE=InnoDB;

-- ============================================================
-- 11. VISTAS DE NEGOCIO
-- Las vistas contienen cálculos; no se duplican como columnas base.
-- ============================================================

CREATE OR REPLACE VIEW vw_precio_vigente AS
SELECT
    pd.id AS precio_dia_id,
    pd.producto_id,
    pd.fecha,
    pv.id AS precio_version_id,
    pv.precio_kg,
    pv.vigente_desde
FROM precios_dia pd
JOIN precio_dia_versiones pv ON pv.precio_dia_id = pd.id
WHERE pv.vigente_desde <= NOW()
  AND NOT EXISTS (
      SELECT 1
      FROM precio_dia_versiones pv2
      WHERE pv2.precio_dia_id = pv.precio_dia_id
        AND pv2.vigente_desde <= NOW()
        AND (
            pv2.vigente_desde > pv.vigente_desde
            OR (pv2.vigente_desde = pv.vigente_desde AND pv2.id > pv.id)
        )
  );

CREATE OR REPLACE VIEW vw_totales_venta_detalle AS
SELECT
    vd.id AS venta_detalle_id,
    vd.venta_id,
    pd.producto_id,
    pv.precio_kg AS precio_referencia_kg,
    vd.precio_aplicado_kg,
    CASE
        WHEN vd.precio_aplicado_kg <> pv.precio_kg THEN 1
        ELSE 0
    END AS precio_modificado,
    COALESCE(SUM(pvta.cantidad_pollos),0) AS cantidad_pollos,
    ROUND(COALESCE(SUM(
        pvta.peso_bruto_kg - (pvta.cantidad_jabas * pvta.tara_unitaria_aplicada_kg)
    ),0),3) AS peso_neto_kg,
    ROUND(
        COALESCE(SUM(
            pvta.peso_bruto_kg - (pvta.cantidad_jabas * pvta.tara_unitaria_aplicada_kg)
        ),0) * vd.precio_aplicado_kg,
        2
    ) AS total_detalle
FROM venta_detalles vd
JOIN precio_dia_versiones pv ON pv.id = vd.precio_version_id
JOIN precios_dia pd ON pd.id = pv.precio_dia_id
LEFT JOIN pesajes_venta pvta ON pvta.venta_detalle_id = vd.id
GROUP BY
    vd.id, vd.venta_id, pd.producto_id, pv.precio_kg,
    vd.precio_aplicado_kg;

CREATE OR REPLACE VIEW vw_totales_venta AS
SELECT
    v.id AS venta_id,
    v.numero_venta,
    v.cliente_id,
    v.usuario_id,
    v.sesion_caja_id,
    v.fecha_venta,
    CASE WHEN v.anulada_at IS NULL THEN 'ACTIVA' ELSE 'ANULADA' END AS estado,
    COALESCE(SUM(tvd.cantidad_pollos),0) AS cantidad_pollos,
    ROUND(COALESCE(SUM(tvd.peso_neto_kg),0),3) AS peso_neto_kg,
    ROUND(COALESCE(SUM(tvd.total_detalle),0),2) AS total_venta
FROM ventas v
LEFT JOIN vw_totales_venta_detalle tvd ON tvd.venta_id = v.id
GROUP BY
    v.id, v.numero_venta, v.cliente_id, v.usuario_id,
    v.sesion_caja_id, v.fecha_venta, v.anulada_at;

CREATE OR REPLACE VIEW vw_saldos_venta AS
SELECT
    tv.venta_id,
    tv.numero_venta,
    tv.cliente_id,
    tv.fecha_venta,
    tv.total_venta,
    ROUND(COALESCE(SUM(
        CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
    ),0),2) AS total_pagado,
    ROUND(
        tv.total_venta -
        COALESCE(SUM(
            CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
        ),0),
        2
    ) AS saldo_pendiente,
    CASE
        WHEN tv.estado='ANULADA' THEN 'ANULADA'
        WHEN COALESCE(SUM(
            CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
        ),0) = 0 THEN 'PENDIENTE'
        WHEN COALESCE(SUM(
            CASE WHEN c.anulada_at IS NULL THEN ac.monto_aplicado ELSE 0 END
        ),0) < tv.total_venta THEN 'PARCIAL'
        ELSE 'SALDADA'
    END AS estado_pago
FROM vw_totales_venta tv
LEFT JOIN aplicacion_cobranzas ac ON ac.venta_id = tv.venta_id
LEFT JOIN cobranzas c ON c.id = ac.cobranza_id
GROUP BY
    tv.venta_id, tv.numero_venta, tv.cliente_id,
    tv.fecha_venta, tv.total_venta, tv.estado;

CREATE OR REPLACE VIEW vw_saldos_cliente AS
SELECT
    cl.id AS cliente_id,
    cl.nombres_razon_social AS cliente,
    ROUND(COALESCE(SUM(
        CASE WHEN sv.saldo_pendiente > 0 THEN sv.saldo_pendiente ELSE 0 END
    ),0),2) AS deuda_total,
    SUM(
        CASE WHEN sv.saldo_pendiente > 0 THEN 1 ELSE 0 END
    ) AS ventas_pendientes
FROM clientes cl
LEFT JOIN vw_saldos_venta sv
    ON sv.cliente_id = cl.id
   AND sv.estado_pago <> 'ANULADA'
GROUP BY cl.id, cl.nombres_razon_social;

CREATE OR REPLACE VIEW vw_resumen_carga AS
SELECT
    cp.id AS carga_id,
    cp.numero_carga,
    cp.proveedor_id,
    cp.producto_id,
    cp.fecha_carga,
    cp.costo_total,
    COALESCE(SUM(pc.cantidad_pollos),0) AS cantidad_pollos,
    COALESCE(SUM(pc.cantidad_jabas),0) AS cantidad_jabas,
    ROUND(COALESCE(SUM(pc.peso_bruto_kg),0),3) AS peso_bruto_kg,
    ROUND(COALESCE(SUM(pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg),0),3) AS tara_total_kg,
    ROUND(COALESCE(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),0),3) AS peso_neto_kg,
    CASE
        WHEN COALESCE(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),0) > 0
        THEN ROUND(cp.costo_total / SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),4)
        ELSE 0
    END AS costo_promedio_kg
FROM cargas_proveedor cp
LEFT JOIN pesajes_carga pc ON pc.carga_id = cp.id
GROUP BY
    cp.id, cp.numero_carga, cp.proveedor_id,
    cp.producto_id, cp.fecha_carga, cp.costo_total;

CREATE OR REPLACE VIEW vw_saldos_carga_proveedor AS
SELECT
    rc.carga_id,
    rc.numero_carga,
    rc.proveedor_id,
    rc.fecha_carga,
    rc.peso_neto_kg,
    rc.costo_total,
    ROUND(COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2) AS total_pagado,
    ROUND(rc.costo_total - COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0),2) AS saldo_pendiente,
    CASE
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) >= rc.costo_total THEN 'SALDADA'
        WHEN rc.fecha_carga < CURDATE() THEN 'PAGO_ATRASADO'
        ELSE 'PENDIENTE_HOY'
    END AS estado_pago,
    CASE
        WHEN COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END),0) < rc.costo_total THEN 1
        ELSE 0
    END AS requiere_alerta
FROM vw_resumen_carga rc
LEFT JOIN pagos_proveedor pp ON pp.carga_id = rc.carga_id
GROUP BY
    rc.carga_id, rc.numero_carga, rc.proveedor_id,
    rc.fecha_carga, rc.peso_neto_kg, rc.costo_total;

CREATE OR REPLACE VIEW vw_resumen_diario_ventas AS
SELECT
    DATE(tv.fecha_venta) AS fecha,
    SUM(CASE WHEN tv.estado='ACTIVA' THEN 1 ELSE 0 END) AS cantidad_ventas,
    SUM(CASE WHEN tv.estado='ACTIVA' THEN tv.cantidad_pollos ELSE 0 END) AS pollos_vendidos,
    ROUND(SUM(CASE WHEN tv.estado='ACTIVA' THEN tv.peso_neto_kg ELSE 0 END),3) AS kg_vendidos,
    ROUND(SUM(
        CASE WHEN tv.estado='ACTIVA' THEN tv.total_venta ELSE 0 END
    ),2) AS total_ventas,
    ROUND(SUM(
        CASE
            WHEN sv.saldo_pendiente > 0 AND tv.estado='ACTIVA'
            THEN sv.saldo_pendiente
            ELSE 0
        END
    ),2) AS saldo_por_cobrar
FROM vw_totales_venta tv
LEFT JOIN vw_saldos_venta sv ON sv.venta_id = tv.venta_id
GROUP BY DATE(tv.fecha_venta);

CREATE OR REPLACE VIEW vw_cobranzas_diarias AS
SELECT
    DATE(c.fecha_pago) AS fecha,
    mp.codigo AS medio_pago,
    mp.nombre AS medio_pago_nombre,
    ROUND(SUM(
        CASE WHEN c.anulada_at IS NULL THEN c.monto_total ELSE 0 END
    ),2) AS total_cobrado
FROM cobranzas c
JOIN medios_pago mp ON mp.id = c.medio_pago_id
GROUP BY DATE(c.fecha_pago), mp.codigo, mp.nombre;

CREATE OR REPLACE VIEW vw_resumen_caja_usuario AS
SELECT
    sc.id AS sesion_caja_id,
    sc.usuario_id,
    sc.fecha_operacion,
    sc.apertura_at,
    sc.cierre_at,
    sc.cerrada_por,
    sc.monto_apertura,
    sc.monto_contado_efectivo,
    CASE WHEN sc.cierre_at IS NULL THEN 'ABIERTA' ELSE 'CERRADA' END AS estado,

    ROUND(COALESCE(SUM(
        CASE
            WHEN c.anulada_at IS NULL AND mp.es_efectivo=TRUE
            THEN c.monto_total ELSE 0
        END
    ),0),2) AS ingresos_efectivo,

    ROUND(COALESCE(SUM(
        CASE
            WHEN c.anulada_at IS NULL AND mp.es_efectivo=FALSE
            THEN c.monto_total ELSE 0
        END
    ),0),2) AS ingresos_otros_medios,

    (
        SELECT ROUND(COALESCE(SUM(pp.monto),0),2)
        FROM pagos_proveedor pp
        JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
        WHERE pp.sesion_caja_id = sc.id
          AND pp.anulada_at IS NULL
          AND mpp.es_efectivo = TRUE
    ) AS egresos_proveedor_efectivo,

    (
        SELECT ROUND(COALESCE(SUM(pp.monto),0),2)
        FROM pagos_proveedor pp
        JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
        WHERE pp.sesion_caja_id = sc.id
          AND pp.anulada_at IS NULL
          AND mpp.es_efectivo = FALSE
    ) AS egresos_proveedor_otros_medios,

    ROUND(
        sc.monto_apertura
        + COALESCE(SUM(
            CASE
                WHEN c.anulada_at IS NULL AND mp.es_efectivo=TRUE
                THEN c.monto_total ELSE 0
            END
        ),0)
        - (
            SELECT COALESCE(SUM(pp.monto),0)
            FROM pagos_proveedor pp
            JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
            WHERE pp.sesion_caja_id = sc.id
              AND pp.anulada_at IS NULL
              AND mpp.es_efectivo = TRUE
        ),
        2
    ) AS efectivo_esperado,

    CASE
        WHEN sc.monto_contado_efectivo IS NULL THEN NULL
        ELSE ROUND(
            sc.monto_contado_efectivo - (
                sc.monto_apertura
                + COALESCE(SUM(
                    CASE
                        WHEN c.anulada_at IS NULL AND mp.es_efectivo=TRUE
                        THEN c.monto_total ELSE 0
                    END
                ),0)
                - (
                    SELECT COALESCE(SUM(pp.monto),0)
                    FROM pagos_proveedor pp
                    JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
                    WHERE pp.sesion_caja_id = sc.id
                      AND pp.anulada_at IS NULL
                      AND mpp.es_efectivo = TRUE
                )
            ),
            2
        )
    END AS diferencia_efectivo,

    ROUND(
        COALESCE(SUM(
            CASE
                WHEN c.anulada_at IS NULL AND mp.es_efectivo=FALSE
                THEN c.monto_total ELSE 0
            END
        ),0)
        - (
            SELECT COALESCE(SUM(pp.monto),0)
            FROM pagos_proveedor pp
            JOIN medios_pago mpp ON mpp.id = pp.medio_pago_id
            WHERE pp.sesion_caja_id = sc.id
              AND pp.anulada_at IS NULL
              AND mpp.es_efectivo = FALSE
        ),
        2
    ) AS neto_otros_medios

FROM sesiones_caja sc
LEFT JOIN cobranzas c ON c.sesion_caja_id = sc.id
LEFT JOIN medios_pago mp ON mp.id = c.medio_pago_id
GROUP BY
    sc.id, sc.usuario_id, sc.fecha_operacion,
    sc.apertura_at, sc.cierre_at, sc.cerrada_por,
    sc.monto_apertura, sc.monto_contado_efectivo;

-- Movimiento unificado de mercadería.
CREATE OR REPLACE VIEW vw_movimientos_mercaderia AS

SELECT
    cp.fecha_carga AS fecha_movimiento,
    cp.producto_id,
    'ENTRADA_CARGA' AS tipo_movimiento,
    cp.id AS referencia_id,
    SUM(pc.cantidad_pollos) AS pollos_entrada,
    0 AS pollos_salida,
    ROUND(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),3) AS kg_entrada,
    0.000 AS kg_salida,
    SUM(pc.cantidad_pollos) AS movimiento_neto_pollos,
    ROUND(SUM(pc.peso_bruto_kg - (pc.cantidad_jabas * pc.tara_unitaria_aplicada_kg)),3) AS movimiento_neto_kg
FROM cargas_proveedor cp
JOIN pesajes_carga pc ON pc.carga_id = cp.id
GROUP BY cp.id, cp.fecha_carga, cp.producto_id

UNION ALL

SELECT
    DATE(v.fecha_venta) AS fecha_movimiento,
    pd.producto_id,
    'VENTA' AS tipo_movimiento,
    v.id AS referencia_id,
    0 AS pollos_entrada,
    SUM(pv.cantidad_pollos) AS pollos_salida,
    0.000 AS kg_entrada,
    ROUND(SUM(
        pv.peso_bruto_kg - (pv.cantidad_jabas * pv.tara_unitaria_aplicada_kg)
    ),3) AS kg_salida,
    -SUM(pv.cantidad_pollos) AS movimiento_neto_pollos,
    -ROUND(SUM(
        pv.peso_bruto_kg - (pv.cantidad_jabas * pv.tara_unitaria_aplicada_kg)
    ),3) AS movimiento_neto_kg
FROM ventas v
JOIN venta_detalles vd ON vd.venta_id = v.id
JOIN precio_dia_versiones pver ON pver.id = vd.precio_version_id
JOIN precios_dia pd ON pd.id = pver.precio_dia_id
JOIN pesajes_venta pv ON pv.venta_detalle_id = vd.id
WHERE v.anulada_at IS NULL
GROUP BY v.id, DATE(v.fecha_venta), pd.producto_id

UNION ALL

SELECT
    DATE(a.fecha_ajuste) AS fecha_movimiento,
    a.producto_id,
    ta.codigo AS tipo_movimiento,
    a.id AS referencia_id,
    CASE WHEN ta.naturaleza='ENTRADA' THEN a.cantidad_pollos ELSE 0 END,
    CASE WHEN ta.naturaleza='SALIDA' THEN a.cantidad_pollos ELSE 0 END,
    CASE WHEN ta.naturaleza='ENTRADA' THEN a.peso_kg ELSE 0.000 END,
    CASE WHEN ta.naturaleza='SALIDA' THEN a.peso_kg ELSE 0.000 END,
    CASE
        WHEN ta.naturaleza='ENTRADA' THEN a.cantidad_pollos
        ELSE -a.cantidad_pollos
    END,
    CASE
        WHEN ta.naturaleza='ENTRADA' THEN a.peso_kg
        ELSE -a.peso_kg
    END
FROM ajustes_mercaderia a
JOIN tipos_ajuste_mercaderia ta ON ta.id = a.tipo_ajuste_id
WHERE a.anulado_at IS NULL;

CREATE OR REPLACE VIEW vw_movimiento_diario_mercaderia AS
SELECT
    fecha_movimiento AS fecha,
    producto_id,
    SUM(pollos_entrada) AS pollos_entrada,
    SUM(pollos_salida) AS pollos_salida,
    ROUND(SUM(kg_entrada),3) AS kg_entrada,
    ROUND(SUM(kg_salida),3) AS kg_salida,

    SUM(CASE
        WHEN tipo_movimiento='ENTRADA_CARGA'
        THEN pollos_entrada ELSE 0 END) AS pollos_recibidos_carga,

    ROUND(SUM(CASE
        WHEN tipo_movimiento='ENTRADA_CARGA'
        THEN kg_entrada ELSE 0 END),3) AS kg_recibidos_carga,

    SUM(CASE
        WHEN tipo_movimiento='VENTA'
        THEN pollos_salida ELSE 0 END) AS pollos_vendidos,

    ROUND(SUM(CASE
        WHEN tipo_movimiento='VENTA'
        THEN kg_salida ELSE 0 END),3) AS kg_vendidos,

    SUM(CASE
        WHEN tipo_movimiento='MERMA'
        THEN pollos_salida ELSE 0 END) AS pollos_merma,

    ROUND(SUM(CASE
        WHEN tipo_movimiento='MERMA'
        THEN kg_salida ELSE 0 END),3) AS kg_merma,

    SUM(movimiento_neto_pollos) AS movimiento_neto_pollos,
    ROUND(SUM(movimiento_neto_kg),3) AS movimiento_neto_kg
FROM vw_movimientos_mercaderia
GROUP BY fecha_movimiento, producto_id;

CREATE OR REPLACE VIEW vw_balance_diario_mercaderia AS
SELECT
    md.fecha,
    md.producto_id,

    COALESCE(
        SUM(md.movimiento_neto_pollos) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ),0
    ) AS saldo_inicial_pollos,

    ROUND(COALESCE(
        SUM(md.movimiento_neto_kg) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
        ),0
    ),3) AS saldo_inicial_kg,

    md.pollos_recibidos_carga,
    md.kg_recibidos_carga,
    md.pollos_vendidos,
    md.kg_vendidos,
    md.pollos_merma,
    md.kg_merma,

    COALESCE(
        SUM(md.movimiento_neto_pollos) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ),0
    ) AS sobrante_final_pollos,

    ROUND(COALESCE(
        SUM(md.movimiento_neto_kg) OVER (
            PARTITION BY md.producto_id
            ORDER BY md.fecha
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ),0
    ),3) AS sobrante_final_kg,

    CASE
        WHEN
            SUM(md.movimiento_neto_pollos) OVER (
                PARTITION BY md.producto_id
                ORDER BY md.fecha
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) < 0
            OR
            SUM(md.movimiento_neto_kg) OVER (
                PARTITION BY md.producto_id
                ORDER BY md.fecha
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) < 0
        THEN 1
        ELSE 0
    END AS requiere_revision

FROM vw_movimiento_diario_mercaderia md;

CREATE OR REPLACE VIEW vw_saldo_mercaderia_actual AS
SELECT
    p.id AS producto_id,
    p.nombre AS producto,
    COALESCE(SUM(m.movimiento_neto_pollos),0) AS pollos_disponibles,
    ROUND(COALESCE(SUM(m.movimiento_neto_kg),0),3) AS kg_disponibles,
    CASE
        WHEN COALESCE(SUM(m.movimiento_neto_pollos),0) < 0
          OR COALESCE(SUM(m.movimiento_neto_kg),0) < 0
        THEN 1 ELSE 0
    END AS requiere_revision
FROM productos p
LEFT JOIN vw_movimientos_mercaderia m ON m.producto_id = p.id
WHERE p.activo = TRUE
GROUP BY p.id, p.nombre;

CREATE OR REPLACE VIEW vw_conciliaciones_mercaderia AS
SELECT
    c.id AS conciliacion_id,
    c.numero_conciliacion,
    c.fecha_operacion,
    c.tipo_conciliacion,
    c.realizada_at,
    c.producto_id,
    c.usuario_id,
    c.cantidad_pollos_sistema,
    c.peso_sistema_kg,
    c.cantidad_pollos_fisico,
    c.peso_fisico_kg,
    (c.cantidad_pollos_fisico - c.cantidad_pollos_sistema) AS diferencia_pollos,
    ROUND(c.peso_fisico_kg - c.peso_sistema_kg,3) AS diferencia_peso_kg,
    CASE
        WHEN (c.cantidad_pollos_fisico - c.cantidad_pollos_sistema) = 0
         AND ABS(c.peso_fisico_kg - c.peso_sistema_kg) < 0.001
        THEN 'CUADRADO'
        ELSE 'CON_DIFERENCIA'
    END AS estado_conciliacion,
    c.observacion,
    c.created_at
FROM conciliaciones_mercaderia c;

-- Cobranzas que todavía no han sido aplicadas totalmente a ventas.
CREATE OR REPLACE VIEW vw_cobranzas_pendientes_aplicar AS
SELECT
    c.id AS cobranza_id,
    c.numero_cobranza,
    c.cliente_id,
    c.fecha_pago,
    c.monto_total,
    ROUND(COALESCE(SUM(ac.monto_aplicado),0),2) AS monto_aplicado,
    ROUND(c.monto_total - COALESCE(SUM(ac.monto_aplicado),0),2) AS monto_sin_aplicar
FROM cobranzas c
LEFT JOIN aplicacion_cobranzas ac ON ac.cobranza_id = c.id
WHERE c.anulada_at IS NULL
GROUP BY c.id, c.numero_cobranza, c.cliente_id, c.fecha_pago, c.monto_total
HAVING monto_sin_aplicar > 0;

-- Alertas operativas básicas para dashboard/administración.
CREATE OR REPLACE VIEW vw_alertas_operativas AS
SELECT
    'PROVEEDOR_PENDIENTE' AS tipo_alerta,
    scp.carga_id AS referencia_id,
    scp.numero_carga AS referencia,
    scp.fecha_carga AS fecha,
    scp.saldo_pendiente AS monto,
    CONCAT('Carga pendiente de pago: ', scp.numero_carga) AS mensaje
FROM vw_saldos_carga_proveedor scp
WHERE scp.requiere_alerta = 1

UNION ALL

SELECT
    'SALDO_MERCADERIA_NEGATIVO',
    sma.producto_id,
    sma.producto,
    CURDATE(),
    NULL,
    CONCAT('Revisar saldo de mercadería: ', sma.producto)
FROM vw_saldo_mercaderia_actual sma
WHERE sma.requiere_revision = 1

UNION ALL

SELECT
    'COBRANZA_SIN_APLICAR',
    cpa.cobranza_id,
    cpa.numero_cobranza,
    DATE(cpa.fecha_pago),
    cpa.monto_sin_aplicar,
    CONCAT('Cobranza con monto sin aplicar: ', cpa.numero_cobranza)
FROM vw_cobranzas_pendientes_aplicar cpa

UNION ALL

SELECT
    'DEUDA_SIN_CLIENTE',
    sv.venta_id,
    sv.numero_venta,
    DATE(sv.fecha_venta),
    sv.saldo_pendiente,
    CONCAT('Venta con saldo pendiente sin cliente: ', sv.numero_venta)
FROM vw_saldos_venta sv
WHERE sv.estado_pago IN ('PENDIENTE','PARCIAL')
  AND sv.cliente_id IS NULL;

-- ============================================================
-- 12. TRIGGERS DE INTEGRIDAD
-- ============================================================

DELIMITER $$

-- Cada usuario solo puede mantener una sesión de caja abierta.
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
END$$

-- Una caja cerrada no puede reabrirse; el cierre conserva trazabilidad.
CREATE TRIGGER trg_caja_no_reabrir_update
BEFORE UPDATE ON sesiones_caja
FOR EACH ROW
BEGIN
    IF OLD.cierre_at IS NOT NULL AND NEW.cierre_at IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Una sesión de caja cerrada no puede reabrirse.';
    END IF;
END$$

-- La versión del precio debe pertenecer a la misma fecha de precios_dia.
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
END$$

-- Las versiones de precio son históricas: no se editan, se crea una nueva versión.
CREATE TRIGGER trg_precio_version_no_update
BEFORE UPDATE ON precio_dia_versiones
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'No se puede editar una versión de precio. Registre una nueva versión.';
END$$

-- La venta debe usar una caja abierta del mismo usuario y del mismo día.
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
END$$

-- Valida precio especial y que el precio del día corresponda a la fecha de venta.
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
END$$

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
END$$

-- Cobranza vinculada a caja: mismo usuario, día y caja abierta.
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
END$$

-- Una cobranza ya registrada conserva sus datos financieros; para corregir se anula.
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
END$$

-- Pago a proveedor: no exceder saldo; si usa caja, debe ser caja abierta del usuario y día.
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
END$$

-- El pago al proveedor es inmutable; para corregir se anula y registra uno nuevo.
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
END$$

-- Una aplicación no puede exceder cobranza/saldo, aplicarse a venta anulada ni a otro cliente.
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
END$$

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
END$$

DELIMITER ;

-- ============================================================
-- 13. DATOS BASE
-- ============================================================

INSERT INTO tipos_documento (codigo,nombre,longitud_maxima) VALUES
('DNI','Documento Nacional de Identidad',8),
('RUC','Registro Único de Contribuyentes',11),
('CE','Carné de Extranjería',20),
('OTRO','Otro documento',20);

INSERT INTO unidades_medida (codigo,nombre,simbolo) VALUES
('KG','Kilogramo','kg');

INSERT INTO medios_pago (codigo,nombre,es_efectivo) VALUES
('EFECTIVO','Efectivo',TRUE),
('YAPE','Yape',FALSE);

INSERT INTO tipos_ajuste_mercaderia
(codigo,nombre,naturaleza,requiere_motivo) VALUES
('SALDO_INICIAL','Saldo inicial','ENTRADA',TRUE),
('MERMA','Merma','SALIDA',TRUE),
('CONSUMO_INTERNO','Consumo interno','SALIDA',TRUE),
('DESCARTE','Descarte','SALIDA',TRUE),
('AJUSTE_POSITIVO','Ajuste positivo','ENTRADA',TRUE),
('AJUSTE_NEGATIVO','Ajuste negativo','SALIDA',TRUE);

INSERT INTO roles (nombre,descripcion) VALUES
('ADMINISTRADOR','Acceso completo al sistema.'),
('CAJA','Ventas, cobranzas, abonos y cierre de caja.'),
('OPERACIONES','Registro de cargas, pesajes y proveedores.'),
('CONSULTA','Consulta de reportes sin modificar operaciones.');

INSERT INTO permisos (codigo,nombre) VALUES
('USUARIOS_GESTIONAR','Gestionar usuarios y roles'),
('PRECIO_DIA_GESTIONAR','Registrar y editar precio del día'),
('CARGAS_REGISTRAR','Registrar cargas y pesajes de proveedor'),
('PROVEEDORES_PAGAR','Registrar pagos a proveedores'),
('PROVEEDORES_PAGO_ANULAR','Anular pagos a proveedores'),
('VENTAS_REGISTRAR','Registrar ventas'),
('VENTAS_ANULAR','Anular ventas'),
('PRECIO_VENTA_EDITAR','Modificar precio para una venta'),
('COBRANZAS_REGISTRAR','Registrar pagos y abonos'),
('COBRANZAS_ANULAR','Anular cobranzas'),
('CAJA_ABRIR_CERRAR','Abrir y cerrar caja'),
('MERCADERIA_AJUSTAR','Registrar mermas y ajustes de mercadería'),
('MERCADERIA_CONCILIAR','Registrar conciliación física'),
('REPORTES_VER','Consultar reportes'),
('AUDITORIA_VER','Consultar auditoría');

INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre='ADMINISTRADOR';

INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN (
    'VENTAS_REGISTRAR',
    'PRECIO_VENTA_EDITAR',
    'COBRANZAS_REGISTRAR',
    'CAJA_ABRIR_CERRAR',
    'REPORTES_VER'
)
WHERE r.nombre='CAJA';

INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN (
    'CARGAS_REGISTRAR',
    'PROVEEDORES_PAGAR',
    'PROVEEDORES_PAGO_ANULAR',
    'MERCADERIA_AJUSTAR',
    'MERCADERIA_CONCILIAR',
    'REPORTES_VER'
)
WHERE r.nombre='OPERACIONES';

INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo='REPORTES_VER'
WHERE r.nombre='CONSULTA';

INSERT INTO productos (nombre,descripcion,unidad_medida_id)
SELECT
    'POLLO',
    'Pollo comercializado por kilogramo',
    id
FROM unidades_medida
WHERE codigo='KG';

-- Taras referenciales: reemplazar por las medidas reales.
INSERT INTO tipos_jaba (nombre,tara_referencial_kg,descripcion) VALUES
('JABA TIPO A',0.000,'Configurar tara real.'),
('JABA TIPO B',0.000,'Configurar tara real.');

INSERT INTO configuracion_empresa (
    id, razon_social, nombre_comercial, mensaje_ticket
) VALUES (
    1,
    'AVÍCOLA - CONFIGURAR',
    'AVÍCOLA',
    'Gracias por su compra.'
);

-- ============================================================
-- 14. CONSULTAS DE USO
-- ============================================================

-- Precio vigente:
-- SELECT * FROM vw_precio_vigente
-- WHERE producto_id = ? AND fecha = CURDATE();

-- Clientes con deuda:
-- SELECT * FROM vw_saldos_cliente
-- WHERE deuda_total > 0
-- ORDER BY deuda_total DESC;

-- Ventas pendientes de un cliente:
-- SELECT * FROM vw_saldos_venta
-- WHERE cliente_id = ? AND saldo_pendiente > 0
-- ORDER BY fecha_venta ASC;

-- Proveedores pendientes:
-- SELECT * FROM vw_saldos_carga_proveedor
-- WHERE requiere_alerta = 1;

-- Saldo de mercadería:
-- SELECT * FROM vw_saldo_mercaderia_actual;

-- Balance diario:
-- SELECT * FROM vw_balance_diario_mercaderia
-- WHERE fecha = CURDATE();

-- Caja:
-- SELECT * FROM vw_resumen_caja_usuario
-- WHERE sesion_caja_id = ?;

-- ============================================================
-- FIN DEL MODELO NORMALIZADO A 3FN
-- ============================================================
