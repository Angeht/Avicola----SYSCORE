<?php

use App\Http\Controllers\AjusteClienteController;
use App\Http\Controllers\AjusteMercaderiaController;
use App\Http\Controllers\AjusteProveedorController;
use App\Http\Controllers\AnulacionAjusteClienteController;
use App\Http\Controllers\AnulacionAjusteMercaderiaController;
use App\Http\Controllers\AnulacionAjusteProveedorController;
use App\Http\Controllers\AnulacionCargaProveedorController;
use App\Http\Controllers\AnulacionCobranzaController;
use App\Http\Controllers\AnulacionPagoProveedorController;
use App\Http\Controllers\AnulacionProcesoBeneficiadoController;
use App\Http\Controllers\AnulacionVentaController;
use App\Http\Controllers\AplicacionAbonoController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AutorizacionEdicionPesajeCargaController;
use App\Http\Controllers\CargaProveedorController;
use App\Http\Controllers\CierreSesionCajaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CobranzaController;
use App\Http\Controllers\ConciliacionMercaderiaController;
use App\Http\Controllers\ConfiguracionEmpresaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PagoProveedorController;
use App\Http\Controllers\PesajeCargaController;
use App\Http\Controllers\PrecioDiaController;
use App\Http\Controllers\ProcesoBeneficiadoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\Profile\PasswordController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RespaldoController;
use App\Http\Controllers\RestauracionRespaldoController;
use App\Http\Controllers\RolActivationController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SesionCajaController;
use App\Http\Controllers\TicketCobranzaController;
use App\Http\Controllers\TicketVentaController;
use App\Http\Controllers\TipoJabaActivationController;
use App\Http\Controllers\TipoJabaController;
use App\Http\Controllers\UsuarioActivationController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\UsuarioPasswordController;
use App\Http\Controllers\UsuarioPinAutorizacionController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VerificacionRespaldoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? to_route('dashboard')
        : to_route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active.user'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/seguridad', [PasswordController::class, 'edit'])->name('profile.password.edit');
    Route::put('/seguridad', [PasswordController::class, 'update'])->name('profile.password.update');

    Route::resource('auditorias', AuditoriaController::class)
        ->only(['index', 'show'])
        ->middleware('permission:AUDITORIA_VER');

    Route::middleware('permission:REPORTES_VER')->group(function (): void {
        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/clientes/{cliente}/estado-cuenta', [ReporteController::class, 'customerAccount'])
            ->name('reportes.customer-account');
        Route::get('/reportes/clientes/{cliente}/estado-cuenta/excel', [ReporteController::class, 'customerAccountCsv'])
            ->name('reportes.customer-account.csv');
        Route::get('/reportes/clientes/{cliente}/estado-cuenta/imprimir', [ReporteController::class, 'customerAccountPrint'])
            ->name('reportes.customer-account.print');
        Route::get('/reportes/clientes/{cliente}/estado-cuenta/ticket', [ReporteController::class, 'customerAccountTicket'])
            ->name('reportes.customer-account.ticket');
        Route::get('/reportes/proveedores/{proveedor}/estado-cuenta', [ReporteController::class, 'supplierAccount'])
            ->name('reportes.supplier-account');
        Route::get('/reportes/proveedores/{proveedor}/estado-cuenta/excel', [ReporteController::class, 'supplierAccountCsv'])
            ->name('reportes.supplier-account.csv');
        Route::get('/reportes/proveedores/{proveedor}/estado-cuenta/imprimir', [ReporteController::class, 'supplierAccountPrint'])
            ->name('reportes.supplier-account.print');
        Route::get('/reportes/proveedores/{proveedor}/estado-cuenta/ticket', [ReporteController::class, 'supplierAccountTicket'])
            ->name('reportes.supplier-account.ticket');
        Route::get('/reportes/{report}/excel', [ReporteController::class, 'csv'])
            ->whereIn('report', ['ventas', 'cuentas-cobrar', 'deudas-proveedores', 'mercaderia', 'caja'])
            ->name('reportes.csv');
        Route::get('/reportes/{report}/imprimir', [ReporteController::class, 'print'])
            ->whereIn('report', ['ventas', 'cuentas-cobrar', 'deudas-proveedores', 'mercaderia', 'caja'])
            ->name('reportes.print');
        Route::get('/reportes/{report}', [ReporteController::class, 'show'])
            ->whereIn('report', ['ventas', 'cuentas-cobrar', 'deudas-proveedores', 'mercaderia', 'caja'])
            ->name('reportes.show');
    });

    Route::middleware('permission:CONFIGURACION_EMPRESA_GESTIONAR')->group(function (): void {
        Route::get('/configuracion', [ConfiguracionEmpresaController::class, 'edit'])
            ->name('configuracion.edit');
        Route::put('/configuracion', [ConfiguracionEmpresaController::class, 'update'])
            ->name('configuracion.update');
    });

    Route::middleware('permission:TIPOS_JABA_GESTIONAR')->group(function (): void {
        Route::resource('tipos-jaba', TipoJabaController::class)
            ->except(['show', 'destroy'])
            ->parameters(['tipos-jaba' => 'tipoJaba']);
        Route::post('/tipos-jaba/{tipoJaba}/activacion', [TipoJabaActivationController::class, 'store'])
            ->name('tipos-jaba.activacion.store');
        Route::delete('/tipos-jaba/{tipoJaba}/activacion', [TipoJabaActivationController::class, 'destroy'])
            ->name('tipos-jaba.activacion.destroy');
    });

    Route::middleware('permission:USUARIOS_GESTIONAR')->group(function (): void {
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
        Route::post('/usuarios/{usuario}/activacion', [UsuarioActivationController::class, 'store'])
            ->name('usuarios.activacion.store');
        Route::delete('/usuarios/{usuario}/activacion', [UsuarioActivationController::class, 'destroy'])
            ->name('usuarios.activacion.destroy');
        Route::get('/usuarios/{usuario}/password', [UsuarioPasswordController::class, 'edit'])
            ->name('usuarios.password.edit');
        Route::put('/usuarios/{usuario}/password', [UsuarioPasswordController::class, 'update'])
            ->name('usuarios.password.update');
        Route::get('/usuarios/{usuario}/pin-autorizacion', [UsuarioPinAutorizacionController::class, 'edit'])
            ->name('usuarios.pin-autorizacion.edit');
        Route::put('/usuarios/{usuario}/pin-autorizacion', [UsuarioPinAutorizacionController::class, 'update'])
            ->name('usuarios.pin-autorizacion.update');

        Route::resource('roles', RolController::class)
            ->except(['show', 'destroy'])
            ->parameters(['roles' => 'rol']);
        Route::post('/roles/{rol}/activacion', [RolActivationController::class, 'store'])
            ->name('roles.activacion.store');
        Route::delete('/roles/{rol}/activacion', [RolActivationController::class, 'destroy'])
            ->name('roles.activacion.destroy');
    });

    Route::prefix('respaldos')
        ->name('respaldos.')
        ->middleware('permission:RESPALDOS_GESTIONAR')
        ->group(function (): void {
            Route::get('/', [RespaldoController::class, 'index'])->name('index');
            Route::post('/', [RespaldoController::class, 'store'])
                ->middleware('throttle:3,10')
                ->name('store');
            Route::get('/{respaldo}/descargar', [RespaldoController::class, 'download'])
                ->name('download');
            Route::post('/{respaldo}/verificacion', [VerificacionRespaldoController::class, 'store'])
                ->middleware('throttle:3,10')
                ->name('verificacion.store');
            Route::get('/{respaldo}/restauracion', [RestauracionRespaldoController::class, 'create'])
                ->name('restauracion.create');
            Route::post('/{respaldo}/restauracion', [RestauracionRespaldoController::class, 'store'])
                ->middleware('throttle:2,10')
                ->name('restauracion.store');
            Route::delete('/{respaldo}', [RespaldoController::class, 'destroy'])
                ->middleware('throttle:5,10')
                ->name('destroy');
        });

    Route::resource('clientes', ClienteController::class)
        ->except('show')
        ->middleware('permission:VENTAS_REGISTRAR,COBRANZAS_REGISTRAR');

    Route::resource('proveedores', ProveedorController::class)
        ->except('show')
        ->parameters(['proveedores' => 'proveedor'])
        ->middleware('permission:CARGAS_REGISTRAR,PROVEEDORES_PAGAR');

    Route::resource('productos', ProductoController::class)
        ->except('show')
        ->middleware('permission:CARGAS_REGISTRAR,PRECIO_DIA_GESTIONAR,MERCADERIA_AJUSTAR');

    Route::resource('precios-dia', PrecioDiaController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['precios-dia' => 'precioDia'])
        ->middleware('permission:PRECIO_DIA_GESTIONAR');

    Route::get('/cargas-proveedor', [CargaProveedorController::class, 'index'])
        ->middleware('permission:CARGAS_REGISTRAR,CARGAS_ANULAR')
        ->name('cargas-proveedor.index');
    Route::get('/cargas-proveedor/create', [CargaProveedorController::class, 'create'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->name('cargas-proveedor.create');
    Route::post('/cargas-proveedor', [CargaProveedorController::class, 'store'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->name('cargas-proveedor.store');
    Route::get('/cargas-proveedor/{cargaProveedor}/pesajes/create', [PesajeCargaController::class, 'create'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->name('cargas-proveedor.pesajes.create');
    Route::post('/cargas-proveedor/{cargaProveedor}/pesajes', [PesajeCargaController::class, 'store'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->name('cargas-proveedor.pesajes.store');
    Route::get('/cargas-proveedor/{cargaProveedor}/pesajes/{pesaje}/autorizacion-edicion', [AutorizacionEdicionPesajeCargaController::class, 'create'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->scopeBindings()
        ->name('cargas-proveedor.pesajes.autorizacion.create');
    Route::post('/cargas-proveedor/{cargaProveedor}/pesajes/{pesaje}/autorizacion-edicion', [AutorizacionEdicionPesajeCargaController::class, 'store'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->scopeBindings()
        ->name('cargas-proveedor.pesajes.autorizacion.store');
    Route::get('/cargas-proveedor/{cargaProveedor}/pesajes/{pesaje}/edit', [PesajeCargaController::class, 'edit'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->scopeBindings()
        ->name('cargas-proveedor.pesajes.edit');
    Route::put('/cargas-proveedor/{cargaProveedor}/pesajes/{pesaje}', [PesajeCargaController::class, 'update'])
        ->middleware('permission:CARGAS_REGISTRAR')
        ->scopeBindings()
        ->name('cargas-proveedor.pesajes.update');
    Route::get('/cargas-proveedor/{cargaProveedor}', [CargaProveedorController::class, 'show'])
        ->middleware('permission:CARGAS_REGISTRAR,CARGAS_ANULAR')
        ->name('cargas-proveedor.show');
    Route::get('/cargas-proveedor/{cargaProveedor}/anulacion', [AnulacionCargaProveedorController::class, 'create'])
        ->middleware('permission:CARGAS_ANULAR')
        ->name('cargas-proveedor.anulacion.create');
    Route::post('/cargas-proveedor/{cargaProveedor}/anulacion', [AnulacionCargaProveedorController::class, 'store'])
        ->middleware('permission:CARGAS_ANULAR')
        ->name('cargas-proveedor.anulacion.store');
    Route::get('/cargas-proveedor/{cargaProveedor}/ajustes/create', [AjusteProveedorController::class, 'create'])
        ->middleware('permission:PROVEEDORES_AJUSTAR')
        ->name('cargas-proveedor.ajustes.create');
    Route::post('/cargas-proveedor/{cargaProveedor}/ajustes', [AjusteProveedorController::class, 'store'])
        ->middleware('permission:PROVEEDORES_AJUSTAR')
        ->name('cargas-proveedor.ajustes.store');
    Route::get('/cargas-proveedor/{cargaProveedor}/ajustes/{ajusteProveedor}/anulacion', [AnulacionAjusteProveedorController::class, 'create'])
        ->middleware('permission:PROVEEDORES_AJUSTAR')
        ->name('cargas-proveedor.ajustes.anulacion.create');
    Route::post('/cargas-proveedor/{cargaProveedor}/ajustes/{ajusteProveedor}/anulacion', [AnulacionAjusteProveedorController::class, 'store'])
        ->middleware('permission:PROVEEDORES_AJUSTAR')
        ->name('cargas-proveedor.ajustes.anulacion.store');

    Route::get('/ventas', [VentaController::class, 'index'])
        ->middleware('permission:VENTAS_REGISTRAR,VENTAS_EDITAR,VENTAS_ANULAR')
        ->name('ventas.index');
    Route::get('/ventas/create', [VentaController::class, 'create'])
        ->middleware('permission:VENTAS_REGISTRAR')
        ->name('ventas.create');
    Route::post('/ventas', [VentaController::class, 'store'])
        ->middleware('permission:VENTAS_REGISTRAR')
        ->name('ventas.store');
    Route::get('/ventas/{venta}/edit', [VentaController::class, 'edit'])
        ->middleware('permission:VENTAS_EDITAR')
        ->name('ventas.edit');
    Route::put('/ventas/{venta}', [VentaController::class, 'update'])
        ->middleware('permission:VENTAS_EDITAR')
        ->name('ventas.update');
    Route::get('/ventas/{venta}', [VentaController::class, 'show'])
        ->middleware('permission:VENTAS_REGISTRAR,VENTAS_EDITAR,VENTAS_ANULAR')
        ->name('ventas.show');
    Route::get('/ventas/{venta}/ticket', TicketVentaController::class)
        ->middleware('permission:VENTAS_REGISTRAR,VENTAS_EDITAR,VENTAS_ANULAR')
        ->name('ventas.ticket');
    Route::get('/ventas/{venta}/anulacion', [AnulacionVentaController::class, 'create'])
        ->middleware('permission:VENTAS_ANULAR')
        ->name('ventas.anulacion.create');
    Route::post('/ventas/{venta}/anulacion', [AnulacionVentaController::class, 'store'])
        ->middleware('permission:VENTAS_ANULAR')
        ->name('ventas.anulacion.store');
    Route::get('/ventas/{venta}/ajustes/create', [AjusteClienteController::class, 'create'])
        ->middleware('permission:CLIENTES_AJUSTAR')
        ->name('ventas.ajustes.create');
    Route::post('/ventas/{venta}/ajustes', [AjusteClienteController::class, 'store'])
        ->middleware('permission:CLIENTES_AJUSTAR')
        ->name('ventas.ajustes.store');
    Route::get('/ventas/{venta}/ajustes/{ajusteCliente}/anulacion', [AnulacionAjusteClienteController::class, 'create'])
        ->middleware('permission:CLIENTES_AJUSTAR')
        ->name('ventas.ajustes.anulacion.create');
    Route::post('/ventas/{venta}/ajustes/{ajusteCliente}/anulacion', [AnulacionAjusteClienteController::class, 'store'])
        ->middleware('permission:CLIENTES_AJUSTAR')
        ->name('ventas.ajustes.anulacion.store');

    Route::get('/cobranzas', [CobranzaController::class, 'index'])
        ->middleware('permission:COBRANZAS_REGISTRAR,COBRANZAS_ANULAR')
        ->name('cobranzas.index');
    Route::get('/cobranzas/create', [CobranzaController::class, 'create'])
        ->middleware('permission:COBRANZAS_REGISTRAR')
        ->name('cobranzas.create');
    Route::post('/cobranzas', [CobranzaController::class, 'store'])
        ->middleware('permission:COBRANZAS_REGISTRAR')
        ->name('cobranzas.store');
    Route::get('/cobranzas/{cobranza}', [CobranzaController::class, 'show'])
        ->middleware('permission:COBRANZAS_REGISTRAR,COBRANZAS_ANULAR')
        ->name('cobranzas.show');
    Route::get('/cobranzas/{cobranza}/ticket', TicketCobranzaController::class)
        ->middleware('permission:COBRANZAS_REGISTRAR,COBRANZAS_ANULAR')
        ->name('cobranzas.ticket');
    Route::get('/cobranzas/{cobranza}/aplicaciones/create', [AplicacionAbonoController::class, 'create'])
        ->middleware('permission:COBRANZAS_REGISTRAR')
        ->name('cobranzas.aplicaciones.create');
    Route::post('/cobranzas/{cobranza}/aplicaciones', [AplicacionAbonoController::class, 'store'])
        ->middleware('permission:COBRANZAS_REGISTRAR')
        ->name('cobranzas.aplicaciones.store');
    Route::get('/cobranzas/{cobranza}/anulacion', [AnulacionCobranzaController::class, 'create'])
        ->middleware('permission:COBRANZAS_ANULAR')
        ->name('cobranzas.anulacion.create');
    Route::post('/cobranzas/{cobranza}/anulacion', [AnulacionCobranzaController::class, 'store'])
        ->middleware('permission:COBRANZAS_ANULAR')
        ->name('cobranzas.anulacion.store');

    Route::get('/mercaderia', [AjusteMercaderiaController::class, 'index'])
        ->middleware('permission:MERCADERIA_AJUSTAR,MERCADERIA_CONCILIAR')
        ->name('mercaderia.index');
    Route::get('/mercaderia/create', [AjusteMercaderiaController::class, 'create'])
        ->middleware('permission:MERCADERIA_AJUSTAR')
        ->name('mercaderia.create');
    Route::post('/mercaderia', [AjusteMercaderiaController::class, 'store'])
        ->middleware('permission:MERCADERIA_AJUSTAR')
        ->name('mercaderia.store');
    Route::get('/mercaderia/{ajusteMercaderia}', [AjusteMercaderiaController::class, 'show'])
        ->middleware('permission:MERCADERIA_AJUSTAR,MERCADERIA_CONCILIAR')
        ->name('mercaderia.show');
    Route::get('/mercaderia/{ajusteMercaderia}/anulacion', [AnulacionAjusteMercaderiaController::class, 'create'])
        ->middleware('permission:MERCADERIA_AJUSTAR')
        ->name('mercaderia.anulacion.create');
    Route::post('/mercaderia/{ajusteMercaderia}/anulacion', [AnulacionAjusteMercaderiaController::class, 'store'])
        ->middleware('permission:MERCADERIA_AJUSTAR')
        ->name('mercaderia.anulacion.store');

    Route::resource('beneficiados', ProcesoBeneficiadoController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['beneficiados' => 'procesoBeneficiado'])
        ->middleware('permission:MERCADERIA_AJUSTAR');
    Route::get('/beneficiados/{procesoBeneficiado}/anulacion', [AnulacionProcesoBeneficiadoController::class, 'create'])
        ->middleware('permission:MERCADERIA_AJUSTAR')
        ->name('beneficiados.anulacion.create');
    Route::post('/beneficiados/{procesoBeneficiado}/anulacion', [AnulacionProcesoBeneficiadoController::class, 'store'])
        ->middleware('permission:MERCADERIA_AJUSTAR')
        ->name('beneficiados.anulacion.store');

    Route::get('/conciliaciones-mercaderia', [ConciliacionMercaderiaController::class, 'index'])
        ->middleware('permission:MERCADERIA_CONCILIAR')
        ->name('conciliaciones-mercaderia.index');
    Route::get('/conciliaciones-mercaderia/create', [ConciliacionMercaderiaController::class, 'create'])
        ->middleware('permission:MERCADERIA_CONCILIAR')
        ->name('conciliaciones-mercaderia.create');
    Route::post('/conciliaciones-mercaderia', [ConciliacionMercaderiaController::class, 'store'])
        ->middleware('permission:MERCADERIA_CONCILIAR')
        ->name('conciliaciones-mercaderia.store');
    Route::get('/conciliaciones-mercaderia/{conciliacionMercaderia}', [ConciliacionMercaderiaController::class, 'show'])
        ->middleware('permission:MERCADERIA_CONCILIAR')
        ->name('conciliaciones-mercaderia.show');

    Route::get('/pagos-proveedor', [PagoProveedorController::class, 'index'])
        ->middleware('permission:PROVEEDORES_PAGAR,PROVEEDORES_PAGO_ANULAR')
        ->name('pagos-proveedor.index');
    Route::get('/pagos-proveedor/create', [PagoProveedorController::class, 'create'])
        ->middleware('permission:PROVEEDORES_PAGAR')
        ->name('pagos-proveedor.create');
    Route::post('/pagos-proveedor', [PagoProveedorController::class, 'store'])
        ->middleware('permission:PROVEEDORES_PAGAR')
        ->name('pagos-proveedor.store');
    Route::get('/pagos-proveedor/{pagoProveedor}', [PagoProveedorController::class, 'show'])
        ->middleware('permission:PROVEEDORES_PAGAR,PROVEEDORES_PAGO_ANULAR')
        ->name('pagos-proveedor.show');
    Route::get('/pagos-proveedor/{pagoProveedor}/anulacion', [AnulacionPagoProveedorController::class, 'create'])
        ->middleware('permission:PROVEEDORES_PAGO_ANULAR')
        ->name('pagos-proveedor.anulacion.create');
    Route::post('/pagos-proveedor/{pagoProveedor}/anulacion', [AnulacionPagoProveedorController::class, 'store'])
        ->middleware('permission:PROVEEDORES_PAGO_ANULAR')
        ->name('pagos-proveedor.anulacion.store');

    Route::middleware('permission:CAJA_ABRIR_CERRAR')->group(function (): void {
        Route::get('/caja/{sesionCaja}/cierre', [CierreSesionCajaController::class, 'create'])
            ->name('caja.cierre.create');
        Route::post('/caja/{sesionCaja}/cierre', [CierreSesionCajaController::class, 'store'])
            ->name('caja.cierre.store');

        Route::resource('caja', SesionCajaController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['caja' => 'sesionCaja']);
    });
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'permission:REPORTES_VER'])
    ->name('dashboard');
