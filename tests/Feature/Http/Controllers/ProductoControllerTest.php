<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('productos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_inventory_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('productos.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_products_by_measurement_unit(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        $kilograms = UnidadMedida::factory()->create([
            'codigo' => 'KG',
            'nombre' => 'Kilogramo',
            'simbolo' => 'kg',
        ]);
        $units = UnidadMedida::factory()->create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'simbolo' => 'und',
        ]);
        Producto::factory()->create(['nombre' => 'POLLO', 'unidad_medida_id' => $kilograms->id]);
        Producto::factory()->create(['nombre' => 'JABA', 'unidad_medida_id' => $units->id]);

        $response = $this->actingAs($user)->get(route('productos.index', [
            'buscar' => 'kilogramo',
            'estado' => 'todos',
        ]));

        $response
            ->assertOk()
            ->assertSee('POLLO')
            ->assertDontSee('JABA');
    }

    public function test_valid_payload_creates_normalized_product(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        $measurementUnit = UnidadMedida::factory()->create();

        $response = $this->actingAs($user)->post(route('productos.store'), [
            'nombre' => '  pollo   beneficiado ',
            'descripcion' => ' Venta   por kilogramo ',
            'unidad_medida_id' => $measurementUnit->id,
            'modalidad_venta' => Producto::MODALIDAD_SOLO_PESO,
            'activo' => '1',
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('productos.index'))
            ->assertSessionHas('status', 'Producto registrado correctamente.');
        $this->assertDatabaseHas('productos', [
            'nombre' => 'POLLO BENEFICIADO',
            'descripcion' => 'Venta por kilogramo',
            'unidad_medida_id' => $measurementUnit->id,
            'modalidad_venta' => Producto::MODALIDAD_SOLO_PESO,
            'activo' => 1,
        ]);
        $this->assertDatabaseMissing('productos', ['created_at' => '2000-01-01 00:00:00']);
    }

    public function test_name_and_measurement_unit_are_required(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');

        $response = $this->actingAs($user)
            ->from(route('productos.create'))
            ->post(route('productos.store'), ['activo' => '1']);

        $response->assertSessionHasErrors([
            'nombre' => 'Ingresa el nombre del producto.',
            'unidad_medida_id' => 'Selecciona la unidad de medida.',
            'modalidad_venta' => 'Selecciona cómo se registrarán las ventas del producto.',
        ]);
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_duplicate_product_name_is_rejected_after_normalization(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        $measurementUnit = UnidadMedida::factory()->create();
        Producto::factory()->create([
            'nombre' => 'POLLO',
            'unidad_medida_id' => $measurementUnit->id,
        ]);

        $response = $this->actingAs($user)
            ->from(route('productos.create'))
            ->post(route('productos.store'), [
                'nombre' => 'pollo',
                'unidad_medida_id' => $measurementUnit->id,
                'modalidad_venta' => Producto::MODALIDAD_PESAJE_VIVO,
                'activo' => '1',
            ]);

        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe un producto con ese nombre.',
        ]);
        $this->assertDatabaseCount('productos', 1);
    }

    public function test_update_can_keep_name_and_reactivate_product(): void
    {
        $user = $this->userWithPermission('MERCADERIA_AJUSTAR');
        $measurementUnit = UnidadMedida::factory()->create();
        $product = Producto::factory()->inactivo()->create([
            'nombre' => 'POLLO',
            'unidad_medida_id' => $measurementUnit->id,
        ]);

        $response = $this->actingAs($user)->put(route('productos.update', $product), [
            'nombre' => 'pollo',
            'descripcion' => 'Producto actualizado',
            'unidad_medida_id' => $measurementUnit->id,
            'modalidad_venta' => Producto::MODALIDAD_SOLO_PESO,
            'activo' => '1',
        ]);

        $response
            ->assertRedirect(route('productos.index'))
            ->assertSessionHas('status', 'Producto actualizado correctamente.');
        $this->assertDatabaseHas('productos', [
            'id' => $product->id,
            'nombre' => 'POLLO',
            'descripcion' => 'Producto actualizado',
            'modalidad_venta' => Producto::MODALIDAD_SOLO_PESO,
            'activo' => 1,
        ]);
    }

    public function test_destroy_deactivates_product_without_deleting_history(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        $product = Producto::factory()->create();

        $response = $this->actingAs($user)->delete(route('productos.destroy', $product));

        $response
            ->assertRedirect(route('productos.index'))
            ->assertSessionHas('status', 'Producto desactivado. Su historial se mantiene disponible.');
        $this->assertModelExists($product);
        $this->assertDatabaseHas('productos', ['id' => $product->id, 'activo' => 0]);
    }

    private function userWithPermission(string $code): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => $code]);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
