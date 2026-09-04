<?php

namespace App\Providers;

use App\Contracts\GestorRespaldos;
use App\Contracts\MotorRespaldoBaseDatos;
use App\Models\AjusteCliente;
use App\Models\AjusteMercaderia;
use App\Models\AjusteProveedor;
use App\Models\AuditableModelObserver;
use App\Models\CargaProveedor;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\ConciliacionMercaderia;
use App\Models\ConfiguracionEmpresa;
use App\Models\ConfiguracionRespaldo;
use App\Models\PagoProveedor;
use App\Models\PesajeCarga;
use App\Models\PesajeVenta;
use App\Models\PrecioDia;
use App\Models\PrecioDiaVersion;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SesionCaja;
use App\Models\TipoJaba;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\GestorRespaldosMySql;
use App\Services\MotorMySql;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MotorRespaldoBaseDatos::class, MotorMySql::class);
        $this->app->bind(GestorRespaldos::class, GestorRespaldosMySql::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        foreach ([
            AjusteCliente::class,
            AjusteMercaderia::class,
            AjusteProveedor::class,
            CargaProveedor::class,
            Cliente::class,
            Cobranza::class,
            ConciliacionMercaderia::class,
            ConfiguracionEmpresa::class,
            ConfiguracionRespaldo::class,
            PagoProveedor::class,
            PesajeCarga::class,
            PesajeVenta::class,
            PrecioDia::class,
            PrecioDiaVersion::class,
            ProcesoBeneficiado::class,
            Producto::class,
            Proveedor::class,
            SesionCaja::class,
            TipoJaba::class,
            Venta::class,
            VentaDetalle::class,
        ] as $auditableModel) {
            $auditableModel::observe(AuditableModelObserver::class);
        }

        View::composer('layouts.app', function (ViewInstance $view): void {
            $authenticatedUser = request()->user();

            if ($authenticatedUser instanceof Usuario) {
                $authenticatedUser->loadMissing('roles.permisos');
            }

            $view->with([
                'authenticatedUser' => $authenticatedUser,
                'company' => ConfiguracionEmpresa::query()->first(),
            ]);
        });
    }
}
