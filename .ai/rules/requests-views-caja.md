---
paths:
  - 'app/{Http/Controllers/*SesionCajaController.php,Http/Requests/*SesionCajaRequest.php,Models/SesionCaja.php,Services/AutorizacionPinAdministrador.php},resources/views/caja/**'
---

# Requests Views Caja

## Autorizar apertura y cierre con PIN administrativo
Abrir o cerrar una jornada de caja exige el PIN de 4 dígitos de un ADMINISTRADOR activo con PIN configurado. Limitar a 5 intentos durante 10 minutos, nunca conservar el PIN como old input y guardar en la sesión de caja qué administrador autorizó la apertura o el cierre.
