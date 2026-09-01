---
paths:
  - 'app/{Http/Controllers,Services}/**/caja*,app/Services/ResumenJornadaCaja.php,resources/views/caja/**'
---

# Caja

## Arrastrar solo efectivo y conciliar todos los medios
La apertura del día precarga el efectivo físico contado en la última jornada cerrada anterior del mismo usuario y el campo sigue editable. Yape, transferencias y otros medios nunca se convierten en efectivo de apertura: se desglosan por separado, pero su neto sí forma parte del resultado general del cierre.
