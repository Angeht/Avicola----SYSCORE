---
paths:
  - 'app/{Http/Controllers/VentaController.php,Http/Requests/*VentaRequest.php,Models/Venta.php}'
---

# Http Controllers Requests

## Editar ventas recuperando su stock original
Una venta activa se edita dentro de una transacción conservando número, vendedor, fecha y sesión originales. Para validar existencia, sumar temporalmente al saldo actual las aves y kg de la propia venta; el nuevo total nunca puede quedar por debajo de cobranzas vigentes. Registrar editor, fecha y motivo, y auditar el reemplazo de detalles/pesajes.
