# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Requests/{StoreClienteRequest.php,UpdateClienteRequest.php,StoreProveedorRequest.php,UpdateProveedorRequest.php,UpdateConfiguracionEmpresaRequest.php} | .ai/rules/app-http-requests.md |
| app/** | .ai/rules/app.md |
| app/{Http/Controllers,Services}/**/caja*,app/Services/ResumenJornadaCaja.php,resources/views/caja/** | .ai/rules/caja.md |
| {app/Http/Controllers/AnulacionCargaProveedorController.php,app/Http/Requests/AnularCargaProveedorRequest.php,app/Models/CargaProveedor.php,resources/views/cargas-proveedor/**} | .ai/rules/cargas-proveedor.md |
| resources/{js/app.js,views/components/load-weighing-row.blade.php} | .ai/rules/components.md |
| app/{Models,Http/Controllers,Http/Requests}/** | .ai/rules/controllers-http-requests.md |
| app/Http/{Controllers,Requests}/** | .ai/rules/controllers-requests.md |
| app/Http/Controllers/ConciliacionMercaderiaController.php, app/Http/Controllers/AplicacionAbonoController.php, app/Http/Controllers/{Usuario*,Rol*}Controller.php, app/Http/Controllers/**/*Proveedor*.php, app/Http/Controllers/*Respaldo*.php, app/Http/Controllers/*CargaController.php, app/Http/Controllers/**, app/Http/Controllers/ReporteController.php | .ai/rules/controllers.md |
| {app/Models/Usuario.php,app/Models/Permiso.php,app/Http/Controllers/RolController.php,app/Http/Requests/*RolRequest.php,database/{migrations,seeders}/**,tests/Feature/**} | .ai/rules/feature.md |
| app/{Http/Controllers/VentaController.php,Http/Requests/*VentaRequest.php,Models/Venta.php} | .ai/rules/http-controllers-requests.md |
| app/Http/Controllers/TipoJaba*.php,app/Http/Requests/*TipoJabaRequest.php | .ai/rules/http-requests.md |
| {app/Http/Controllers/CobranzaController.php,app/Http/Requests/StoreCobranzaRequest.php,resources/views/cobranzas/create.blade.php,resources/js/app.js} | .ai/rules/js.md |
| resources/views/{layouts/app.blade.php,proveedores/**,tipos-jaba/**,configuracion/**} | .ai/rules/layouts.md |
| app/Models/Usuario.php,app/Http/Middleware/EnsureActiveUser.php | .ai/rules/middleware.md |
| app/{Models/Usuario.php,Http/Controllers/AnulacionVentaController.php,Http/Requests/AnularVentaRequest.php} | .ai/rules/models-controllers-requests.md |
| {app/Http/Controllers/*PesajeCargaController.php,app/Http/Requests/*PesajeCargaRequest.php,app/Services/AutorizacionEdicionPesajeCarga.php,resources/views/cargas-proveedor/**,app/Models/Usuario.php} | .ai/rules/models.md |
| resources/{js/app.js,views/components/load-weighing-row.blade.php,views/cargas-proveedor/pesajes/create.blade.php} | .ai/rules/pesajes.md |
| resources/{js/app.js,views/components/sale-*.blade.php,views/ventas/**,views/productos/**} | .ai/rules/productos.md |
| app/Models/AuditableModelObserver.php,app/Providers/AppServiceProvider.php | .ai/rules/providers.md |
| app/Http/Controllers/ReporteController.php,resources/views/reportes/**, app/Http/Controllers/ReporteController.php,resources/views/reportes/customer-account-ticket.blade.php, app/Http/Controllers/ReporteController.php,resources/views/reportes/supplier-account*.blade.php | .ai/rules/reportes.md |
| app/{Http/Controllers/*SesionCajaController.php,Http/Requests/*SesionCajaRequest.php,Models/SesionCaja.php,Services/AutorizacionPinAdministrador.php},resources/views/caja/** | .ai/rules/requests-views-caja.md |
| app/Models/ConfiguracionEmpresa.php,app/Http/Controllers/ConfiguracionEmpresaController.php,app/Http/Requests/UpdateConfiguracionEmpresaRequest.php | .ai/rules/requests.md |
| resources/{js/app.js,views/**} | .ai/rules/resourcesjs.md |
| routes/console.php,routes/web.php,app/Http/Controllers/RespaldoController.php,resources/views/respaldos/** | .ai/rules/respaldos.md |
| app/Services/*Respaldo*.php | .ai/rules/services.md |
| app/Http/Controllers/{SesionCajaController.php,CierreSesionCajaController.php},app/Services/ResumenJornadaCaja.php,resources/views/caja/** | .ai/rules/views-caja.md |
| {app/Http/Controllers/Ajuste*Controller.php,app/Http/Requests/StoreAjuste*Request.php,resources/views/ajustes-*/**,resources/js/app.js} | .ai/rules/views-js.md |
| app/Http/Controllers/PagoProveedorController.php,app/Http/Controllers/ReporteController.php,app/Http/Requests/StorePagoProveedorRequest.php,resources/views/pagos-proveedor/**,resources/views/reportes/** | .ai/rules/views-reportes.md |
| resources/{views/**,js/app.js} | .ai/rules/views.md |
