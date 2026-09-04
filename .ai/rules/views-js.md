---
paths:
  - '{app/Http/Controllers/Ajuste*Controller.php,app/Http/Requests/StoreAjuste*Request.php,resources/views/ajustes-*/**,resources/js/app.js}'
---

# Views Js

## Capturar el resultado, no el importe del ajuste
En descuentos de cliente y proveedor, el operador ingresa el nuevo saldo pendiente y el servidor calcula la diferencia. En devoluciones no se solicita un importe: se registra la mercadería y el servidor calcula el ajuste con el peso por el precio aplicado de venta o costo de carga; nunca confiar en un monto enviado por el cliente.
