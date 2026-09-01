---
paths:
  - 'app/{Models/Usuario.php,Http/Controllers/AnulacionVentaController.php,Http/Requests/AnularVentaRequest.php}'
---

# Models Controllers Requests

## Eliminar ventas solo con roles administrativos
La eliminación de una venta es una anulación auditada, nunca un borrado físico. Solo roles activos ADMINISTRADOR y ADMINISTRADOR OPERATIVO pueden ejecutarla, aunque otro rol reciba VENTAS_ANULAR; CAJA puede editar con VENTAS_EDITAR pero no eliminar.
