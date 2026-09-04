<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AvicolaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::unprepared(<<<'SQL'
INSERT INTO tipos_documento (codigo,nombre,longitud_maxima) VALUES
('DNI','Documento Nacional de Identidad',8),
('RUC','Registro Único de Contribuyentes',11),
('CE','Carné de Extranjería',20),
('OTRO','Otro documento',20);
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO unidades_medida (codigo,nombre,simbolo) VALUES
('KG','Kilogramo','kg');
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO medios_pago (codigo,nombre,es_efectivo) VALUES
('EFECTIVO','Efectivo',TRUE),
('YAPE','Yape',FALSE);
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO tipos_ajuste_mercaderia
(codigo,nombre,naturaleza,requiere_motivo) VALUES
('SALDO_INICIAL','Saldo inicial','ENTRADA',TRUE),
('MERMA','Merma','SALIDA',TRUE),
('CONSUMO_INTERNO','Consumo interno','SALIDA',TRUE),
('DESCARTE','Descarte','SALIDA',TRUE),
('AJUSTE_POSITIVO','Ajuste positivo','ENTRADA',TRUE),
('AJUSTE_NEGATIVO','Ajuste negativo','SALIDA',TRUE);
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO roles (nombre,descripcion) VALUES
('ADMINISTRADOR','Acceso completo al sistema.'),
('CAJA','Ventas, cobranzas, abonos y cierre de caja.'),
('OPERACIONES','Registro de cargas, pesajes y proveedores.'),
('CONSULTA','Consulta de reportes sin modificar operaciones.');
SQL);

            DB::unprepared(<<<'SQL'
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
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
CROSS JOIN permisos p
WHERE r.nombre='ADMINISTRADOR';
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN (
    'VENTAS_REGISTRAR',
    'VENTAS_EDITAR',
    'PRECIO_VENTA_EDITAR',
    'COBRANZAS_REGISTRAR',
    'CLIENTES_AJUSTAR',
    'CAJA_ABRIR_CERRAR',
    'REPORTES_VER'
)
WHERE r.nombre='CAJA';
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo IN (
    'CARGAS_REGISTRAR',
    'PROVEEDORES_PAGAR',
    'PROVEEDORES_AJUSTAR',
    'PROVEEDORES_PAGO_ANULAR',
    'MERCADERIA_AJUSTAR',
    'MERCADERIA_CONCILIAR',
    'REPORTES_VER'
)
WHERE r.nombre='OPERACIONES';
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO rol_permiso (rol_id,permiso_id)
SELECT r.id,p.id
FROM roles r
JOIN permisos p ON p.codigo='REPORTES_VER'
WHERE r.nombre='CONSULTA';
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO productos (nombre,descripcion,unidad_medida_id)
SELECT
    'POLLO',
    'Pollo comercializado por kilogramo',
    id
FROM unidades_medida
WHERE codigo='KG';
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO tipos_jaba (nombre,tara_referencial_kg,descripcion) VALUES
('JABA TIPO A',0.000,'Configurar tara real.'),
('JABA TIPO B',0.000,'Configurar tara real.');
SQL);

            DB::unprepared(<<<'SQL'
INSERT INTO configuracion_empresa (
    id, razon_social, nombre_comercial, mensaje_ticket
) VALUES (
    1,
    'AVÍCOLA - CONFIGURAR',
    'AVÍCOLA',
    'Gracias por su compra.'
);
SQL);
        });
    }
}
