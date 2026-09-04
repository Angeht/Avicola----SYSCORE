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

class AnulacionAjusteProveedorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cancelling_provider_return_restores_balance_and_inventory(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_AJUSTAR');
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => 10,
            'peso_bruto_kg' => 20,
        ]);
        $this->actingAs($user)->post(route('cargas-proveedor.ajustes.store', $load), [
            'tipo' => 'DEVOLUCION',
            'monto' => '50.00',
            'motivo' => 'Mercadería devuelta al proveedor por calidad.',
            'cantidad_pollos' => 2,
            'peso_kg' => '4.000',
        ]);
        $adjustment = AjusteProveedor::query()->firstOrFail();

        $this->actingAs($user)->post(route('cargas-proveedor.ajustes.anulacion.store', [$load, $adjustment]), [
            'motivo_anulacion' => 'La devolución fue registrada por duplicado.',
        ])->assertRedirect(route('cargas-proveedor.show', $load));

        $this->assertNotNull($adjustment->fresh()->anulado_at);
        $this->assertNotNull($adjustment->ajusteMercaderia()->firstOrFail()->anulado_at);
        $this->assertDatabaseHas('vw_saldos_carga_proveedor', [
            'carga_id' => $load->id,
            'saldo_pendiente' => 500.00,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $load->producto_id,
            'pollos_disponibles' => 10,
            'kg_disponibles' => 20.000,
        ]);
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
