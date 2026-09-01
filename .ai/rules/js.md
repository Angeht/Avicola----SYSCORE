---
paths:
  - '{app/Http/Controllers/CobranzaController.php,app/Http/Requests/StoreCobranzaRequest.php,resources/views/cobranzas/create.blade.php,resources/js/app.js}'
---

# Js

## Cobranza directa sobre la deuda del cliente
Registrar cobranza siempre selecciona un cliente y muestra su deuda consolidada; el operador solo ingresa el monto pagado, parcial o completo, sin elegir ventas ni tipo de cobranza. El servidor limita el pago a la deuda actual y lo aplica automáticamente a las ventas pendientes más antiguas, conservando aplicacion_cobranzas únicamente como trazabilidad interna.
