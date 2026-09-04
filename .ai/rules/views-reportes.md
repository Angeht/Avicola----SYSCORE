---
paths:
  - 'app/Http/Controllers/PagoProveedorController.php,app/Http/Controllers/ReporteController.php,app/Http/Requests/StorePagoProveedorRequest.php,resources/views/pagos-proveedor/**,resources/views/reportes/**'
---

# Views Reportes

## Pagos consolidados por proveedor
En la interfaz, los pagos se registran contra la deuda total del proveedor. El servidor distribuye el monto entre sus cargas vigentes más antiguas y conserva un registro pagos_proveedor por carga para trazabilidad. Las pantallas financieras deben presentar deuda, cargas, pagos y ajustes consolidados por proveedor.
