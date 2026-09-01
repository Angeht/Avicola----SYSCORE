---
paths:
  - 'app/Http/Controllers/TipoJaba*.php,app/Http/Requests/*TipoJabaRequest.php'
---

# Http Requests

## Taras positivas y activación separada
Las jabas nuevas o editadas requieren tara_referencial_kg >= 0.001 kg. El estado activo no se acepta en el formulario principal: se cambia únicamente mediante TipoJabaActivationController para conservar historial y auditoría.
