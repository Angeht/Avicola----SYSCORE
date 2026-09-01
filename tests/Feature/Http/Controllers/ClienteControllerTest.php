<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ClienteControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('clientes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_commercial_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('clientes.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_clients_and_rendered_values_are_escaped(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');
        $dangerousName = 'Cliente objetivo <script>alert(1)</script>';
        Cliente::factory()->create(['nombres_razon_social' => $dangerousName]);
        Cliente::factory()->create(['nombres_razon_social' => 'Cliente oculto']);

        $response = $this->actingAs($user)->get(route('clientes.index', [
            'buscar' => 'objetivo',
            'estado' => 'todos',
        ]));

        $response
            ->assertOk()
            ->assertSee($dangerousName)
            ->assertDontSee($dangerousName, false)
            ->assertDontSee('Cliente oculto');
    }

    public function test_valid_payload_creates_normalized_client(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'DNI',
            'longitud_maxima' => 8,
        ]);

        $response = $this->actingAs($user)->post(route('clientes.store'), [
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => '12345678',
            'nombres_razon_social' => '  Ana   Torres  ',
            'telefono' => '999111222',
            'direccion' => ' Av. Central   123 ',
            'observacion' => ' Cliente   frecuente ',
            'activo' => '1',
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('status', 'Cliente registrado correctamente.');
        $this->assertDatabaseHas('clientes', [
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => '12345678',
            'nombres_razon_social' => 'Ana Torres',
            'telefono' => '999111222',
            'direccion' => 'Av. Central 123',
            'observacion' => 'Cliente frecuente',
            'activo' => 1,
        ]);
        $this->assertDatabaseMissing('clientes', ['created_at' => '2000-01-01 00:00:00']);
    }

    public function test_name_is_required(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');

        $response = $this->actingAs($user)
            ->from(route('clientes.create'))
            ->post(route('clientes.store'), ['activo' => '1']);

        $response
            ->assertRedirect(route('clientes.create'))
            ->assertSessionHasErrors([
                'nombres_razon_social' => 'Ingresa el nombre o razón social del cliente.',
            ]);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_phone_requires_exactly_nine_numeric_digits(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');

        foreach (['98765432', '9876543210', '98765A321'] as $invalidPhone) {
            $this->actingAs($user)
                ->from(route('clientes.create'))
                ->post(route('clientes.store'), [
                    'nombres_razon_social' => 'Cliente con teléfono inválido',
                    'telefono' => $invalidPhone,
                    'activo' => '1',
                ])
                ->assertSessionHasErrors([
                    'telefono' => 'El teléfono debe contener exactamente 9 dígitos numéricos.',
                ]);
        }

        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_document_number_requires_type_and_respects_configured_length(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'DNI',
            'longitud_maxima' => 8,
        ]);

        $this->actingAs($user)
            ->from(route('clientes.create'))
            ->post(route('clientes.store'), [
                'nro_documento' => '12345678',
                'nombres_razon_social' => 'Cliente sin tipo',
                'activo' => '1',
            ])
            ->assertSessionHasErrors([
                'tipo_documento_id' => 'Selecciona el tipo de documento.',
            ]);

        $this->actingAs($user)
            ->from(route('clientes.create'))
            ->post(route('clientes.store'), [
                'tipo_documento_id' => $documentType->id,
                'nro_documento' => '123456789',
                'nombres_razon_social' => 'Cliente documento largo',
                'activo' => '1',
            ])
            ->assertSessionHasErrors([
                'nro_documento' => 'El número de documento no puede superar 8 caracteres para DNI.',
            ]);

        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_duplicate_document_is_rejected(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create();
        Cliente::factory()->conDocumento($documentType)->create(['nro_documento' => 'DOC-001']);

        $response = $this->actingAs($user)
            ->from(route('clientes.create'))
            ->post(route('clientes.store'), [
                'tipo_documento_id' => $documentType->id,
                'nro_documento' => 'DOC-001',
                'nombres_razon_social' => 'Cliente duplicado',
                'activo' => '1',
            ]);

        $response->assertSessionHasErrors([
            'nro_documento' => 'Ya existe un cliente con ese tipo y número de documento.',
        ]);
        $this->assertDatabaseMissing('clientes', ['nombres_razon_social' => 'Cliente duplicado']);
    }

    public function test_update_can_keep_document_and_reactivate_client(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create();
        $client = Cliente::factory()->conDocumento($documentType)->inactivo()->create([
            'nro_documento' => 'DOC-002',
        ]);

        $response = $this->actingAs($user)->put(route('clientes.update', $client), [
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => 'DOC-002',
            'nombres_razon_social' => 'Cliente reactivado',
            'telefono' => null,
            'direccion' => null,
            'observacion' => null,
            'activo' => '1',
        ]);

        $response
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('status', 'Cliente actualizado correctamente.');
        $this->assertDatabaseHas('clientes', [
            'id' => $client->id,
            'nro_documento' => 'DOC-002',
            'nombres_razon_social' => 'Cliente reactivado',
            'activo' => 1,
        ]);
    }

    public function test_destroy_deactivates_client_without_deleting_history(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');
        $client = Cliente::factory()->create();

        $response = $this->actingAs($user)->delete(route('clientes.destroy', $client));

        $response
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('status', 'Cliente desactivado. Su historial se mantiene disponible.');
        $this->assertModelExists($client);
        $this->assertDatabaseHas('clientes', ['id' => $client->id, 'activo' => 0]);
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
