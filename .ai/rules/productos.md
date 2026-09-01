---
paths:
  - 'resources/{js/app.js,views/components/sale-*.blade.php,views/ventas/**,views/productos/**}'
---

# Productos

## Formulario de venta según modalidad del producto
Las opciones de producto exponen `data-sale-mode`. `PESAJE_VIVO` muestra aves, jabas y tara; `SOLO_PESO` oculta y pone esos campos en cero, mostrando únicamente peso vendido. Mostrar todo producto con precio vigente aunque no tenga stock y advertir la disponibilidad; el servidor conserva el bloqueo de sobreventa.
