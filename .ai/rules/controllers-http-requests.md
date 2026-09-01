---
paths:
  - 'app/{Models,Http/Controllers,Http/Requests}/**'
---

# Controllers Http Requests

## Límite de privilegios en usuarios y roles
Solo ADMINISTRADOR puede gestionar cuentas o el rol ADMINISTRADOR. Los demás usuarios con USUARIOS_GESTIONAR solo pueden crear, asignar o modificar roles cuyos permisos sean un subconjunto de sus propios permisos activos; revalidar también dentro de la transacción.

## La modalidad de venta pertenece al producto
Usar `productos.modalidad_venta`, nunca el nombre, para distinguir `PESAJE_VIVO` y `SOLO_PESO`. El pesaje vivo controla aves y kg; solo peso guarda aves, jabas y tara en cero y controla únicamente kg. Mantener la validación de existencia al confirmar la venta.
