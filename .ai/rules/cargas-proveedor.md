---
paths:
  - '{app/Http/Controllers/AnulacionCargaProveedorController.php,app/Http/Requests/AnularCargaProveedorRequest.php,app/Models/CargaProveedor.php,resources/views/cargas-proveedor/**}'
---

# Cargas Proveedor

## Anular cargas exige PIN administrativo
La anulación de una carga requiere el PIN de 4 dígitos de un ADMINISTRADOR activo, reutiliza AutorizacionPinAdministrador y registra por separado al operador y al administrador autorizador. El PIN no se conserva en old input ni auditoría y mantiene el límite de 5 intentos por 10 minutos.
