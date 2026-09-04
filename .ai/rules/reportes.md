---
paths:
  - 'app/Http/Controllers/ReporteController.php,resources/views/reportes/**'
  - 'app/Http/Controllers/ReporteController.php,resources/views/reportes/customer-account-ticket.blade.php'
  - 'app/Http/Controllers/ReporteController.php,resources/views/reportes/supplier-account*.blade.php'
---

# Reportes

## Reportes sobre vistas consolidadas
Los reportes deben reutilizar las vistas vw_* existentes. La salida para Excel es CSV UTF-8 con protección contra fórmulas; PDF se entrega mediante la vista imprimible del navegador mientras no se aprueben dependencias de XLSX/PDF nativo.

## Estado de cuenta consolidado por cliente
El reporte cuentas-cobrar muestra una fila por cliente y calcula el saldo al corte con todas sus ventas activas menos todas sus cobranzas vigentes. Un ABONO reduce la deuda consolidada aunque aún no tenga aplicaciones; aplicacion_cobranzas se conserva para distribuir el pago entre ventas, validar topes, auditar y revertir anulaciones.

## Estado de cuenta consolidado por cliente
El estado de cuenta individual debe sumar todas las ventas activas del cliente y restar todas sus cobranzas no anuladas hasta la fecha de corte, sin depender de la distribución interna en aplicaciones. Muestre ventas y abonos cronológicamente con saldo acumulado, y conserve el acceso desde el resumen de cuentas por cobrar.

## Ticket térmico del estado de cuenta
El estado de cuenta ofrece ticket térmico de 80 mm con cliente, corte, movimientos, totales y saldo. Limitar a 200 movimientos y avisar si se trunca; Excel/CSV o PDF conservan el historial ampliado.

## El ticket muestra solo el ciclo de deuda vigente
Para acortar el ticket, localizar el último movimiento cuyo saldo acumulado quedó exactamente en cero y mostrar únicamente los movimientos posteriores con totales recalculados. Si el último movimiento saldó la cuenta, el ticket queda sin movimientos hasta la próxima venta. La pantalla completa, CSV y PDF conservan todo el historial.

## Exportar el estado de cuenta del proveedor en tres formatos
El detalle consolidado del proveedor ofrece CSV completo y vista imprimible/PDF con todos los movimientos hasta el corte. El ticket de 80 mm muestra solo el ciclo vigente posterior al último saldo cero, conserva la referencia de carga y limita la salida a 200 movimientos con aviso de truncamiento.
