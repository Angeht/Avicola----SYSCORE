---
paths:
  - 'app/Services/*Respaldo*.php'
---

# Services

## Restauraciones MySQL con verificación y reversión
Los respaldos se guardan en almacenamiento local privado con rutas generadas por el servidor, tamaño y SHA-256. Nunca aceptar rutas de archivos desde la solicitud. Solo restaurar copias disponibles que hayan pasado una restauración aislada; crear antes una copia preventiva y revertirla automáticamente si falla el origen.
