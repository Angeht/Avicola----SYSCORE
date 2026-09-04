---
paths:
  - 'resources/{js/app.js,views/**}'
---

# Resourcesjs

## Taras sin ceros decimales redundantes
Las taras conservan hasta 3 decimales de precisión, pero la interfaz no fuerza ceros finales: 2.400 se muestra como 2,4 y 2.875 como 2,875. Los inputs type=number usan punto decimal y tampoco se rellenan con ceros; borrar el valor debe dejarlo vacío para poder reescribirlo.
