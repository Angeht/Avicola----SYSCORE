---
paths:
  - 'app/Models/AuditableModelObserver.php,app/Providers/AppServiceProvider.php'
---

# Providers

## Auditar operaciones web sin secretos
La auditoría automática se ejecuta solo durante solicitudes HTTP autenticadas y excluye contraseñas, tokens e timestamps técnicos. Usuario, Rol y AplicacionCobranza conservan auditoría manual para registrar pivotes y evitar duplicados.
