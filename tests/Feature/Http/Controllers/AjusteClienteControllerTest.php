<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteCliente;
use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AjusteClienteControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_discount_reduces_sale_balance_without_creating_a_collection(): void
    {
        $user = $this->userWithPermission('CLIENTES_AJUSTAR');
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => Cliente::factory()]);

        $this->actingAs($user)->post(route('ventas.ajustes.store', $sale), [
            'tipo' => 'DESCUENTO',
            'nuevo_saldo' => '174,50',
            'motivo' => 'Descuento comercial acordado con el cliente.',
        ])->assertRedirect(route('ventas.show', $sale));

        $this->assertDatabaseHas('ajustes_cliente', [
            'venta_id' => $sale->id,
            'tipo' => 'DESCUENTO',
            'monto' => 25.50,
            'usuario_id' => $user->id,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $sale->id,
            'total_ajustado' => 25.50,
            'saldo_pendiente' => 174.50,
        ]);
        $this->assertDatabaseCount('cobranzas', 0);
    }

    public function test_customer_return_adds_inventory_and_reduces_the_balance(): void
    {
        $user = $this->userWithPermission('CLIENTES_AJUSTAR');
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => Cliente::factory()]);
        $productId = DB::table('venta_detalles as vd')
            ->join('precio_dia_versiones as pv', 'pv.id', '=', 'vd.precio_version_id')
            ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
            ->where('vd.venta_id', $sale->id)
            ->value('pd.producto_id');
        $stockBefore = DB::table('vw_saldo_mercaderia_actual')->where('producto_id', $productId)->firstOrFail();

        $this->actingAs($user)->post(route('ventas.ajustes.store', $sale), [
            'tipo' => 'DEVOLUCION',
            'monto' => '1.00',
            'motivo' => 'Devolución aceptada por diferencia de calidad.',
            'producto_id' => $productId,
            'cantidad_pollos' => 2,
            'peso_kg' => '4.000',
        ])->assertRedirect(route('ventas.show', $sale));

        $adjustment = AjusteCliente::query()->firstOrFail();
        $this->assertNotNull($adjustment->ajuste_mercaderia_id);
        $this->assertSame('40.00', $adjustment->monto);
        $stockAfter = DB::table('vw_saldo_mercaderia_actual')->where('producto_id', $productId)->firstOrFail();
        $this->assertSame((int) $stockBefore->pollos_disponibles + 2, (int) $stockAfter->pollos_disponibles);
        $this->assertSame((float) $stockBefore->kg_disponibles + 4.0, (float) $stockAfter->kg_disponibles);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $sale->id,
            'saldo_pendiente' => 160.00,
        ]);
    }

    public function test_adjustment_form_requests_new_balance_for_discount_and_no_recognized_amount_for_return(): void
    {
        $user = $this->userWithPermission('CLIENTES_AJUSTAR');
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => Cliente::factory()]);

        $this->actingAs($user)
            ->get(route('ventas.ajustes.create', $sale))
            ->assertOk()
            ->assertSee('Nuevo saldo pendiente')
            ->assertSee('name="nuevo_saldo"', false)
            ->assertDontSee('Importe reconocido')
            ->assertDontSee('name="monto"', false)
            ->assertSee('Ajuste calculado');
    }

    public function test_discount_rejects_a_new_balance_that_does_not_reduce_the_debt(): void
    {
        $user = $this->userWithPermission('CLIENTES_AJUSTAR');
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => Cliente::factory()]);

        $this->actingAs($user)
            ->from(route('ventas.ajustes.create', $sale))
            ->post(route('ventas.ajustes.store', $sale), [
                'tipo' => 'DESCUENTO',
                'nuevo_saldo' => '200.00',
                'motivo' => 'Descuento comercial sin reducción efectiva.',
            ])
            ->assertSessionHasErrors([
                'nuevo_saldo' => 'El nuevo saldo debe ser menor que el saldo pendiente actual.',
            ]);

        $this->assertDatabaseCount('ajustes_cliente', 0);
    }

    public function test_user_without_adjustment_permission_is_forbidden(): void
    {
        $sale = Venta::factory()->conTotal(100)->create();

        $this->actingAs(Usuario::factory()->create())
            ->get(route('ventas.ajustes.create', $sale))
            ->assertForbidden();
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
