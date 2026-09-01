---
paths:
  - 'app/Http/Controllers/{SesionCajaController.php,CierreSesionCajaController.php},app/Services/ResumenJornadaCaja.php,resources/views/caja/**'
---

# Views Caja

## Resultado general de la jornada
El saldo sugerido para una nueva apertura proviene únicamente del efectivo contado del último cierre anterior del usuario y sigue siendo editable. Los medios no efectivos se muestran separados por canal; su neto se suma al efectivo esperado o contado para obtener el resultado general.
