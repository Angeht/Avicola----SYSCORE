---
paths:
  - 'app/Models/Usuario.php,app/Http/Middleware/EnsureActiveUser.php'
---

# Middleware

## Revocar accesos por estado
Un usuario inactivo no puede continuar una sesión autenticada. Los roles inactivos conservan sus asignaciones históricas, pero no conceden permisos hasta ser reactivados.
