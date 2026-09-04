<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProveedorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('proveedores.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_supply_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('proveedores.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_suppliers(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        Proveedor::factory()->create(['nombre_razon_social' => 'Granja El Objetivo']);
        Proveedor::factory()->create(['nombre_razon_social' => 'Proveedor Oculto']);

        $response = $this->actingAs($user)->get(route('proveedores.index', [
            'buscar' => 'objetivo',
            'estado' => 'todos',
        ]));

        $response
            ->assertOk()
            ->assertSee('Granja El Objetivo')
            ->assertDontSee('Proveedor Oculto');
    }

    public function test_crate_and_tare_management_is_available_from_the_supplier_catalog(): void
    {
        $user = $this->userWithPermissions('CARGAS_REGISTRAR', 'TIPOS_JABA_GESTIONAR');

        $response = $this->actingAs($user)->get(route('proveedores.index'));

        $response
            ->assertOk()
            ->assertSee('Jabas y taras')
            ->assertSee(route('tipos-jaba.index'), false);
    }

    public function test_valid_payload_creates_normalized_supplier(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'RUC',
            'longitud_maxima' => 11,
        ]);

        $response = $this->actingAs($user)->post(route('proveedores.store'), [
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => '20123456789',
            'nombre_razon_social' => '  Granja   San José  ',
            'telefono' => '987000111',
            'numero_cuenta' => '  bcp 191-12345678-0-12 ',
            'direccion' => ' Carretera   Norte ',
            'activo' => '1',
        ]);

        $response
            ->assertRedirect(route('proveedores.index'))
            ->assertSessionHas('status', 'Proveedor registrado correctamente.');
        $this->assertDatabaseHas('proveedores', [
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => '20123456789',
            'nombre_razon_social' => 'Granja San José',
            'telefono' => '987000111',
            'numero_cuenta' => 'BCP 191-12345678-0-12',
            'direccion' => 'Carretera Norte',
            'activo' => 1,
        ]);
    }

    public function test_name_is_required(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');

        $response = $this->actingAs($user)
            ->from(route('proveedores.create'))
            ->post(route('proveedores.store'), ['activo' => '1']);

        $response->assertSessionHasErrors([
            'nombre_razon_social' => 'Ingresa el nombre o razón social del proveedor.',
        ]);
        $this->assertDatabaseCount('proveedores', 0);
    }

    public function test_phone_requires_exactly_nine_numeric_digits(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');

        $this->actingAs($user)
            ->from(route('proveedores.create'))
            ->post(route('proveedores.store'), [
                'nombre_razon_social' => 'Proveedor con teléfono inválido',
                'telefono' => '987-000-111',
                'activo' => '1',
            ])
            ->assertSessionHasErrors([
                'telefono' => 'El teléfono debe contener exactamente 9 dígitos numéricos.',
            ]);

        $this->assertDatabaseCount('proveedores', 0);
    }

    public function test_duplicate_document_is_rejected(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create();
        Proveedor::factory()->conDocumento($documentType)->create(['nro_documento' => 'RUC-001']);

        $response = $this->actingAs($user)
            ->from(route('proveedores.create'))
            ->post(route('proveedores.store'), [
                'tipo_documento_id' => $documentType->id,
                'nro_documento' => 'RUC-001',
                'nombre_razon_social' => 'Proveedor duplicado',
                'activo' => '1',
            ]);

        $response->assertSessionHasErrors([
            'nro_documento' => 'Ya existe un proveedor con ese tipo y número de documento.',
        ]);
        $this->assertDatabaseMissing('proveedores', ['nombre_razon_social' => 'Proveedor duplicado']);
    }

    public function test_update_can_keep_document_and_reactivate_supplier(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $documentType = TipoDocumento::factory()->create();
        $supplier = Proveedor::factory()->conDocumento($documentType)->inactivo()->create([
            'nro_documento' => 'RUC-002',
            'numero_cuenta' => 'CCI 002-12345678901234567890',
        ]);

        $response = $this->actingAs($user)->put(route('proveedores.update', $supplier), [
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => 'RUC-002',
            'nombre_razon_social' => 'Proveedor reactivado',
            'telefono' => null,
            'numero_cuenta' => ' BCP 191-00000000-0-00 ',
            'direccion' => null,
            'activo' => '1',
        ]);

        $response
            ->assertRedirect(route('proveedores.index'))
            ->assertSessionHas('status', 'Proveedor actualizado correctamente.');
        $this->assertDatabaseHas('proveedores', [
            'id' => $supplier->id,
            'nro_documento' => 'RUC-002',
            'nombre_razon_social' => 'Proveedor reactivado',
            'numero_cuenta' => 'BCP 191-00000000-0-00',
            'activo' => 1,
        ]);
    }

    public function test_destroy_deactivates_supplier_without_deleting_history(): void
    {
        $user = $this->userWithPermission('CARGAS_REGISTRAR');
        $supplier = Proveedor::factory()->create();

        $response = $this->actingAs($user)->delete(route('proveedores.destroy', $supplier));

        $response
            ->assertRedirect(route('proveedores.index'))
            ->assertSessionHas('status', 'Proveedor desactivado. Su historial se mantiene disponible.');
        $this->assertModelExists($supplier);
        $this->assertDatabaseHas('proveedores', ['id' => $supplier->id, 'activo' => 0]);
    }

    private function userWithPermission(string $code): Usuario
    {
        return $this->userWithPermissions($code);
    }

    private function userWithPermissions(string ...$codes): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permissions = collect($codes)
            ->map(fn (string $code): Permiso => Permiso::query()->firstOrCreate(
                ['codigo' => $code],
                ['nombre' => $code, 'descripcion' => "Permiso $code"],
            ));

        $role->permisos()->attach($permissions->pluck('id')->all());
        $user->roles()->attach($role);

        return $user;
    }
}
