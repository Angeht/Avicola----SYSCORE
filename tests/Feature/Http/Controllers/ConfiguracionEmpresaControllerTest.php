<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ConfiguracionEmpresa;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConfiguracionEmpresaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('configuracion.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_management_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('configuracion.edit'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_company_configuration_with_escaped_content(): void
    {
        $user = $this->userWithManagementPermission();
        $dangerousName = "AVÍCOLA <script>alert('xss')</script>";
        ConfiguracionEmpresa::factory()->create([
            'razon_social' => $dangerousName,
            'nombre_comercial' => 'AVÍCOLA SEGURA',
        ]);
        TipoDocumento::factory()->create(['codigo' => 'RUC', 'nombre' => 'Registro tributario']);

        $response = $this->actingAs($user)->get(route('configuracion.edit'));

        $response
            ->assertOk()
            ->assertSee('Configuración de la empresa')
            ->assertSee($dangerousName)
            ->assertDontSee($dangerousName, false)
            ->assertSee(route('tipos-jaba.index'), false)
            ->assertSee('RUC');
    }

    public function test_valid_payload_updates_normalized_company_data_and_records_audit(): void
    {
        $user = $this->userWithManagementPermission();
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'RUC',
            'longitud_maxima' => 11,
        ]);
        ConfiguracionEmpresa::factory()->create([
            'razon_social' => 'AVÍCOLA - CONFIGURAR',
            'nombre_comercial' => 'AVÍCOLA',
        ]);

        $response = $this->actingAs($user)->put(route('configuracion.update'), [
            'id' => 9,
            'razon_social' => '  avícola   san miguel s.a.c. ',
            'nombre_comercial' => '  avícola   san miguel ',
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => ' 20123456789 ',
            'direccion' => ' Jr. Los   Álamos 123 ',
            'telefono' => '999888777',
            'mensaje_ticket' => ' Gracias   por su preferencia. ',
            'updated_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('configuracion.edit'))
            ->assertSessionHas('status', 'Configuración de la empresa actualizada correctamente.');
        $this->assertDatabaseHas('configuracion_empresa', [
            'id' => 1,
            'razon_social' => 'AVÍCOLA SAN MIGUEL S.A.C.',
            'nombre_comercial' => 'AVÍCOLA SAN MIGUEL',
            'tipo_documento_id' => $documentType->id,
            'nro_documento' => '20123456789',
            'direccion' => 'Jr. Los Álamos 123',
            'telefono' => '999888777',
            'mensaje_ticket' => 'Gracias por su preferencia.',
        ]);
        $this->assertDatabaseMissing('configuracion_empresa', ['id' => 9]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'configuracion_empresa',
            'registro_id' => 1,
            'accion' => 'UPDATE',
        ]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'razon_social',
            'valor_anterior' => 'AVÍCOLA - CONFIGURAR',
            'valor_nuevo' => 'AVÍCOLA SAN MIGUEL S.A.C.',
        ]);
        $this->assertDatabaseMissing('auditoria_detalles', [
            'campo' => 'updated_at',
            'valor_nuevo' => '2000-01-01 00:00:00',
        ]);
    }

    public function test_company_name_and_legal_name_are_required(): void
    {
        $user = $this->userWithManagementPermission();
        ConfiguracionEmpresa::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('configuracion.edit'))
            ->put(route('configuracion.update'), []);

        $response->assertSessionHasErrors([
            'razon_social' => 'Ingresa la razón social de la empresa.',
            'nombre_comercial' => 'Ingresa el nombre comercial de la empresa.',
        ]);
    }

    public function test_document_number_respects_selected_document_type_length(): void
    {
        $user = $this->userWithManagementPermission();
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'DNI',
            'longitud_maxima' => 8,
        ]);
        ConfiguracionEmpresa::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('configuracion.edit'))
            ->put(route('configuracion.update'), [
                'razon_social' => 'AVÍCOLA PRUEBA',
                'nombre_comercial' => 'AVÍCOLA',
                'tipo_documento_id' => $documentType->id,
                'nro_documento' => '123456789',
            ]);

        $response->assertSessionHasErrors([
            'nro_documento' => 'El número de documento no puede superar 8 caracteres para DNI.',
        ]);
        $this->assertDatabaseMissing('configuracion_empresa', ['nro_documento' => '123456789']);
    }

    public function test_company_phone_requires_exactly_nine_numeric_digits(): void
    {
        $user = $this->userWithManagementPermission();
        ConfiguracionEmpresa::factory()->create();

        $this->actingAs($user)
            ->from(route('configuracion.edit'))
            ->put(route('configuracion.update'), [
                'razon_social' => 'AVÍCOLA PRUEBA',
                'nombre_comercial' => 'AVÍCOLA',
                'telefono' => '99988877A',
            ])
            ->assertSessionHasErrors([
                'telefono' => 'El teléfono debe contener exactamente 9 dígitos numéricos.',
            ]);
    }

    private function userWithManagementPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $permission = Permiso::query()
            ->where('codigo', 'CONFIGURACION_EMPRESA_GESTIONAR')
            ->firstOrFail();

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
