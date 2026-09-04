---
paths:
  - '{app/Models/Usuario.php,app/Models/Permiso.php,app/Http/Controllers/RolController.php,app/Http/Requests/*RolRequest.php,database/{migrations,seeders}/**,tests/Feature/**}'
---

# Feature

## Configuración y respaldos exclusivos del administrador del sistema
CONFIGURACION_EMPRESA_GESTIONAR y RESPALDOS_GESTIONAR (incluida la restauración) solo pueden ser efectivos y asignarse al rol estructural ADMINISTRADOR. ADMINISTRADOR OPERATIVO nunca recibe esos permisos; los formularios de roles personalizados deben ocultarlos y rechazarlos. Roles base: CAJA concentra ventas, cobranzas, pagos a proveedores y caja; OPERACIONES concentra cargas, ajustes de proveedor, mercadería y tipos de jaba; CONSULTA solo reportes.
