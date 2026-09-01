---
paths:
  - 'resources/{js/app.js,views/components/load-weighing-row.blade.php}'
---

# Components

## Seleccionar jaba antes de la cantidad
En cargas de proveedor, el selector de tipo de jaba permanece habilitado aunque la cantidad sea cero. La selección conserva su tara referencial para que el operador pueda elegir primero la jaba y luego ingresar la cantidad; el backend sigue ignorando jaba y tara cuando la cantidad final es cero.

## Separar tara referencial del campo editable
En pesajes de cargas, las opciones de tipo de jaba usan `data-reference-tare` y el input editable usa `data-tare`. No reutilizar el mismo selector: `querySelector('[data-tare]')` debe resolver siempre el input para que se ejecuten la tara automática y los totales en vivo.
