<?php

namespace Tests\Feature;

use App\Models\AjusteMercaderia;
use App\Models\Auditoria;
use App\Models\CargaProveedor;
use App\Models\Cobranza;
use App\Models\ConciliacionMercaderia;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\ProcesoBeneficiado;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalDateDefaultsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operational_histories_default_to_current_date(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithOperationalPermissions();

        $todaySale = Venta::factory()->create(['numero_venta' => 'VENTA-HOY', 'fecha_venta' => now()]);
        $oldSale = Venta::factory()->create(['numero_venta' => 'VENTA-ANTERIOR', 'fecha_venta' => now()->subDay()]);

        $todayLoad = CargaProveedor::factory()->create(['numero_carga' => 'CARGA-HOY', 'fecha_carga' => today()]);
        $oldLoad = CargaProveedor::factory()->create(['numero_carga' => 'CARGA-ANTERIOR', 'fecha_carga' => today()->subDay()]);

        $todayPayment = PagoProveedor::factory()->create([
            'numero_pago' => 'PAGO-HOY',
            'carga_id' => $todayLoad->id,
            'pagado_at' => now(),
        ]);
        $oldPayment = PagoProveedor::factory()->create([
            'numero_pago' => 'PAGO-ANTERIOR',
            'carga_id' => $oldLoad->id,
            'pagado_at' => now()->subDay(),
        ]);

        $todayCollection = Cobranza::factory()->create(['numero_cobranza' => 'COBRANZA-HOY', 'fecha_pago' => now()]);
        $oldCollection = Cobranza::factory()->create(['numero_cobranza' => 'COBRANZA-ANTERIOR', 'fecha_pago' => now()->subDay()]);

        $todayAdjustment = AjusteMercaderia::factory()->create(['numero_ajuste' => 'AJUSTE-HOY', 'fecha_ajuste' => now()]);
        $oldAdjustment = AjusteMercaderia::factory()->create(['numero_ajuste' => 'AJUSTE-ANTERIOR', 'fecha_ajuste' => now()->subDay()]);

        $todayConciliation = ConciliacionMercaderia::factory()->create([
            'numero_conciliacion' => 'CONCILIACION-HOY',
            'fecha_operacion' => today(),
            'realizada_at' => now(),
        ]);
        $oldConciliation = ConciliacionMercaderia::factory()->create([
            'numero_conciliacion' => 'CONCILIACION-ANTERIOR',
            'fecha_operacion' => today()->subDay(),
            'realizada_at' => now()->subDay(),
        ]);

        $todayProcess = ProcesoBeneficiado::factory()->create(['numero_proceso' => 'BENEFICIADO-HOY', 'procesado_at' => now()]);
        $oldProcess = ProcesoBeneficiado::factory()->create(['numero_proceso' => 'BENEFICIADO-ANTERIOR', 'procesado_at' => now()->subDay()]);

        $todayCashSession = SesionCaja::factory()->cerrada()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => today(),
            'apertura_at' => now(),
        ]);
        $oldCashSession = SesionCaja::factory()->cerrada()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => today()->subDay(),
            'apertura_at' => now()->subDay(),
        ]);

        $todayAudit = Auditoria::factory()->create(['tabla_afectada' => 'REGISTRO_HOY', 'created_at' => now()]);
        $oldAudit = Auditoria::factory()->create(['tabla_afectada' => 'REGISTRO_ANTERIOR', 'created_at' => now()->subDay()]);

        $expectations = [
            ['ventas.index', $todaySale->numero_venta, $oldSale->numero_venta],
            ['cargas-proveedor.index', $todayLoad->numero_carga, $oldLoad->numero_carga],
            ['pagos-proveedor.index', $todayPayment->numero_pago, $oldPayment->numero_pago],
            ['cobranzas.index', $todayCollection->numero_cobranza, $oldCollection->numero_cobranza],
            ['mercaderia.index', $todayAdjustment->numero_ajuste, $oldAdjustment->numero_ajuste],
            ['conciliaciones-mercaderia.index', $todayConciliation->numero_conciliacion, $oldConciliation->numero_conciliacion],
            ['beneficiados.index', $todayProcess->numero_proceso, $oldProcess->numero_proceso],
            ['caja.index', "Jornada #{$todayCashSession->id}", "Jornada #{$oldCashSession->id}"],
            ['auditorias.index', route('auditorias.show', $todayAudit), route('auditorias.show', $oldAudit)],
        ];

        foreach ($expectations as [$routeName, $todayMarker, $oldMarker]) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee($todayMarker)
                ->assertDontSee($oldMarker);
        }
    }

    public function test_an_explicit_empty_date_shows_the_complete_history(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithOperationalPermissions();
        $todaySale = Venta::factory()->create(['numero_venta' => 'VENTA-HOY', 'fecha_venta' => now()]);
        $oldSale = Venta::factory()->create(['numero_venta' => 'VENTA-ANTERIOR', 'fecha_venta' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('ventas.index', ['fecha' => '']))
            ->assertOk()
            ->assertSee($todaySale->numero_venta)
            ->assertSee($oldSale->numero_venta);
    }

    private function userWithOperationalPermissions(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permissionCodes = [
            'AUDITORIA_VER',
            'CAJA_ABRIR_CERRAR',
            'CARGAS_REGISTRAR',
            'COBRANZAS_REGISTRAR',
            'MERCADERIA_AJUSTAR',
            'MERCADERIA_CONCILIAR',
            'PROVEEDORES_PAGAR',
            'VENTAS_REGISTRAR',
        ];

        $permissions = collect($permissionCodes)->map(fn (string $code): Permiso => Permiso::query()->firstOrCreate(
            ['codigo' => $code],
            ['nombre' => "Permiso $code"],
        ));

        $role->permisos()->attach($permissions->pluck('id')->all());
        $user->roles()->attach($role);

        return $user;
    }
}
