---
paths:
  - 'resources/{views/**,js/app.js}'
---

# Views

## Precios unitarios sin ceros redundantes
Los precios y costos por kilogramo conservan hasta 4 decimales internamente, pero en pantalla muestran al menos 2 y eliminan solo ceros finales redundantes. Un valor 7.2500 se muestra como S/ 7,25; los decimales significativos adicionales se conservan.
