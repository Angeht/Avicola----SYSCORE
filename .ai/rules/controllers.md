---
paths:
  - app/Http/Controllers/ConciliacionMercaderiaController.php
  - app/Http/Controllers/AplicacionAbonoController.php
  - 'app/Http/Controllers/{Usuario*,Rol*}Controller.php'
  - 'app/Http/Controllers/**/*Proveedor*.php'
  - 'app/Http/Controllers/*Respaldo*.php'
  - 'app/Http/Controllers/*CargaController.php'
  - 'app/Http/Controllers/**'
  - app/Http/Controllers/ReporteController.php
---

# Controllers

## Separar diferencias mixtas de conciliación
Una conciliación puede generar hasta dos ajustes vinculados. Si la diferencia de aves y la de peso tienen signos opuestos, crea un ajuste positivo y otro negativo para no ocultar ninguno de los dos movimientos.

## Aplicar abonos sin mover caja
La aplicación posterior de un ABONO distribuye su saldo mediante aplicacion_cobranzas y registra al operador en auditorías. No crea otra cobranza ni modifica caja, porque el dinero ya ingresó al registrar el abono.

## Conservar un administrador operativo
No se permite desactivar la cuenta propia, retirar el rol ADMINISTRADOR de la sesión actual ni dejar el sistema sin un administrador activo. El rol ADMINISTRADOR no se renombra ni se desactiva y conserva todos los permisos.

## Anular cargas conserva historia y revierte efectos
Una carga de proveedor solo puede anularse cuando no tiene pagos vigentes. La anulación conserva carga y pesajes, registra responsable/fecha/motivo, y excluye la carga de stock, deuda, alertas y nuevos pagos; nunca se elimina el registro.

## Autorización reforzada para restaurar respaldos
Toda gestión usa RESPALDOS_GESTIONAR. Restaurar exige contraseña actual y la frase RESTAURAR, invalida la sesión al completar y no debe exponer mensajes internos de MySQL en las operaciones normales.

## Autorizar la carga antes de registrar pesajes
Las cargas de proveedor se crean primero con costo_kg y costo_total en cero. Los pesajes se agregan después sobre una carga existente y cada bloque debe recalcular costo_total con todo el peso neto acumulado × costo_kg. No se agregan pesajes a cargas anuladas ni con pagos vigentes.

## Historiales operativos abren en la jornada actual
Los índices de operaciones muestran la fecha actual cuando no se envía un filtro de fecha. Una fecha válida consulta esa jornada y una fecha enviada explícitamente vacía permite consultar todo el historial.

## Normalizar collation en historiales unidos
La base heredada mezcla utf8mb4_0900_ai_ci en tablas operativas con utf8mb4_unicode_ci en ajustes. Todo campo textual proyectado por UNION/UNION ALL en ReporteController debe convertirse explícitamente a utf8mb4 y COLLATE utf8mb4_unicode_ci para evitar el error MySQL 1271.
