<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteProveedor;
use App\Models\CargaProveedor;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AjusteProveedorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_provider_discount_reduces_load_balance_without_moving_cash(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_AJUSTAR');
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);

        $this->actingAs($user)->post(route('cargas-proveedor.ajustes.store', $load), [
            'tipo' => 'DESCUENTO',
            'nuevo_saldo' => '465.00',
            'motivo' => 'Descuento acordado por diferencia de calidad.',
        ])->assertRedirect(route('cargas-proveedor.show', $load));

        $this->assertDatabaseHas('ajustes_proveedor', [
            'carga_id' => $load->id,
            'tipo' => 'DESCUENTO',
            'monto' => 35.00,
        ]);
        $this->assertDatabaseHas('vw_saldos_carga_proveedor', [
            'carga_id' => $load->id,
            'total_ajustado' => 35.00,
            'saldo_pendiente' => 465.00,
        ]);
        $this->assertDatabaseCount('pagos_proveedor', 0);
    }

    public function test_provider_return_removes_inventory_and_reduces_load_balance(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_AJUSTAR');
        $load = CargaProveedor::factory()->create([
            'costo_kg' => 12.50,
            'costo_total' => 500,
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => 10,
            'peso_bruto_kg' => 20,
        ]);

        $this->actingAs($user)->post(route('cargas-proveedor.ajustes.store', $load), [
            'tipo' => 'DEVOLUCION',
            'monto' => '1.00',
            'motivo' => 'Mercadería devuelta al proveedor por calidad.',
            'cantidad_pollos' => 2,
            'peso_kg' => '4.000',
        ])->assertRedirect(route('cargas-proveedor.show', $load));

        $adjustment = AjusteProveedor::query()->firstOrFail();
        $this->assertNotNull($adjustment->ajuste_mercaderia_id);
        $this->assertSame('50.00', $adjustment->monto);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $load->producto_id,
            'pollos_disponibles' => 8,
            'kg_disponibles' => 16.000,
        ]);
        $this->assertDatabaseHas('vw_saldos_carga_proveedor', [
            'carga_id' => $load->id,
            'saldo_pendiente' => 450.00,
        ]);
    }

    public function test_adjustment_form_requests_new_balance_and_calculates_returns_from_merchandise(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_AJUSTAR');
        $load = CargaProveedor::factory()->create([
            'costo_kg' => 12.50,
            'costo_total' => 500,
        ]);

        $this->actingAs($user)
            ->get(route('cargas-proveedor.ajustes.create', $load))
            ->assertOk()
            ->assertSee('Nuevo saldo pendiente')
            ->assertSee('name="nuevo_saldo"', false)
            ->assertDontSee('Importe reconocido')
            ->assertDontSee('name="monto"', false)
            ->assertSee('Ajuste calculado');
    }

    public function test_user_without_adjustment_permission_is_forbidden(): void
    {
        $load = CargaProveedor::factory()->create(['costo_total' => 100]);

        $this->actingAs(Usuario::factory()->create())
            ->get(route('cargas-proveedor.ajustes.create', $load))
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
