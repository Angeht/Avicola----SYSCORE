---
paths:
  - '{app/Http/Controllers/*PesajeCargaController.php,app/Http/Requests/*PesajeCargaRequest.php,app/Services/AutorizacionEdicionPesajeCarga.php,resources/views/cargas-proveedor/**,app/Models/Usuario.php}'
---

# Models

## Autorizar edición de pesajes con PIN administrativo
Editar un pesaje de carga exige CARGAS_REGISTRAR y una autorización temporal de 10 minutos, vinculada al operador y al pesaje, emitida con el PIN de 4 dígitos de un ADMINISTRADOR activo. El PIN se guarda solo como hash, se limita a 5 intentos y nunca se registra ni se conserva como old input. Una edición se bloquea si la carga está anulada o tiene pagos vigentes, registra operador y administrador, y recalcula el costo total dentro de la transacción.

## Autorizar edición de pesajes con PIN administrativo
Editar un pesaje de carga exige CARGAS_REGISTRAR y una autorización temporal de 10 minutos, vinculada al operador y al pesaje, emitida con el PIN de 4 dígitos de un ADMINISTRADOR activo. El PIN se guarda solo como hash, se limita a 5 intentos y nunca se registra ni se conserva como old input. La edición se bloquea si la carga está anulada; si hay pagos vigentes, solo se permite cuando el costo total corregido cubre todo lo ya pagado. Registrar operador y administrador y recalcular el costo dentro de la transacción.

## Permitir correcciones seguras en cargas pagadas
Esta regla reemplaza la restricción anterior que bloqueaba toda edición con pagos vigentes. Una carga pagada sí permite corregir un pesaje con PIN administrativo, siempre que el costo total recalculado no quede por debajo del total de pagos vigentes; las cargas anuladas continúan bloqueadas.
