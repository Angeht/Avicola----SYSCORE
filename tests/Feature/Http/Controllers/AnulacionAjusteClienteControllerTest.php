<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteCliente;
use App\Models\AjusteMercaderia;
use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnulacionAjusteClienteControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cancelling_customer_return_restores_balance_and_inventory(): void
    {
        $user = $this->userWithPermission('CLIENTES_AJUSTAR');
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => Cliente::factory()]);
        $productId = DB::table('venta_detalles as vd')
            ->join('precio_dia_versiones as pv', 'pv.id', '=', 'vd.precio_version_id')
            ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
            ->where('vd.venta_id', $sale->id)
            ->value('pd.producto_id');
        AjusteMercaderia::factory()->create([
            'producto_id' => $productId,
            'tipo_ajuste_id' => TipoAjusteMercaderia::factory()->create()->id,
            'cantidad_pollos' => 10,
            'peso_kg' => 20,
            'usuario_id' => $user->id,
        ]);
        $stockBefore = DB::table('vw_saldo_mercaderia_actual')->where('producto_id', $productId)->firstOrFail();

        $this->actingAs($user)->post(route('ventas.ajustes.store', $sale), [
            'tipo' => 'DEVOLUCION',
            'monto' => '40.00',
            'motivo' => 'Devolución aceptada por diferencia de calidad.',
            'producto_id' => $productId,
            'cantidad_pollos' => 2,
            'peso_kg' => '4.000',
        ]);
        $adjustment = AjusteCliente::query()->firstOrFail();

        $this->actingAs($user)->post(route('ventas.ajustes.anulacion.store', [$sale, $adjustment]), [
            'motivo_anulacion' => 'La devolución fue registrada por duplicado.',
        ])->assertRedirect(route('ventas.show', $sale));

        $this->assertNotNull($adjustment->fresh()->anulado_at);
        $this->assertNotNull($adjustment->ajusteMercaderia()->firstOrFail()->anulado_at);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $sale->id,
            'saldo_pendiente' => 200.00,
        ]);
        $stockAfter = DB::table('vw_saldo_mercaderia_actual')->where('producto_id', $productId)->firstOrFail();
        $this->assertSame((int) $stockBefore->pollos_disponibles, (int) $stockAfter->pollos_disponibles);
        $this->assertSame((float) $stockBefore->kg_disponibles, (float) $stockAfter->kg_disponibles);
    }

    private function userWithPermission(string $permissionCode): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::query()->where('codigo', $permissionCode)->firstOrFail();

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
