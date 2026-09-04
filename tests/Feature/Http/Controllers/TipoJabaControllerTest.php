<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoJaba;
use App\Models\Usuario;
use DOMDocument;
use DOMElement;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TipoJabaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tipos-jaba.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_management_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('tipos-jaba.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_filter_and_search_crate_types_with_escaped_content(): void
    {
        $user = $this->userWithManagementPermission();
        $dangerousDescription = "Jaba <script>alert('xss')</script>";
        TipoJaba::factory()->create([
            'nombre' => 'JABA AZUL',
            'descripcion' => $dangerousDescription,
        ]);
        TipoJaba::factory()->inactivo()->create(['nombre' => 'JABA ROJA']);

        $response = $this->actingAs($user)->get(route('tipos-jaba.index', [
            'buscar' => 'azul',
            'estado' => 'activos',
        ]));

        $response
            ->assertOk()
            ->assertSee('JABA AZUL')
            ->assertSee($dangerousDescription)
            ->assertDontSee($dangerousDescription, false)
            ->assertDontSee('JABA ROJA')
            ->assertSee(route('tipos-jaba.index'), false)
            ->assertDontSee('Configuración');
    }

    public function test_index_displays_only_meaningful_tare_decimals(): void
    {
        $user = $this->userWithManagementPermission();
        TipoJaba::factory()->create([
            'nombre' => 'JABA TARA CORTA',
            'tara_referencial_kg' => '2.400',
        ]);
        TipoJaba::factory()->create([
            'nombre' => 'JABA TARA PRECISA',
            'tara_referencial_kg' => '2.875',
        ]);

        $response = $this->actingAs($user)->get(route('tipos-jaba.index'));

        $response
            ->assertSee('2,4')
            ->assertSee('2,875')
            ->assertDontSee('2.400')
            ->assertDontSee('2.875');
    }

    public function test_valid_payload_creates_normalized_active_crate_type_and_records_audit(): void
    {
        $user = $this->userWithManagementPermission();

        $response = $this->actingAs($user)->post(route('tipos-jaba.store'), [
            'nombre' => '  jaba   azul grande ',
            'tara_referencial_kg' => '2.350',
            'descripcion' => ' Plástico   reforzado ',
            'activo' => '0',
            'created_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('tipos-jaba.index'))
            ->assertSessionHas('status', 'Tipo de jaba registrado correctamente.');
        $this->assertDatabaseHas('tipos_jaba', [
            'nombre' => 'JABA AZUL GRANDE',
            'tara_referencial_kg' => '2.350',
            'descripcion' => 'Plástico reforzado',
            'activo' => 1,
        ]);
        $this->assertDatabaseMissing('tipos_jaba', ['created_at' => '2000-01-01 00:00:00']);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'tipos_jaba',
            'accion' => 'INSERT',
        ]);
    }

    public function test_zero_tare_is_rejected(): void
    {
        $user = $this->userWithManagementPermission();

        $response = $this->actingAs($user)
            ->from(route('tipos-jaba.create'))
            ->post(route('tipos-jaba.store'), [
                'nombre' => 'JABA SIN TARA',
                'tara_referencial_kg' => '0.000',
            ]);

        $response->assertSessionHasErrors([
            'tara_referencial_kg' => 'La tara debe ser mayor que 0.000 kg.',
        ]);
        $this->assertDatabaseCount('tipos_jaba', 0);
    }

    public function test_duplicate_name_is_rejected_after_normalization(): void
    {
        $user = $this->userWithManagementPermission();
        TipoJaba::factory()->create(['nombre' => 'JABA VERDE']);

        $response = $this->actingAs($user)
            ->from(route('tipos-jaba.create'))
            ->post(route('tipos-jaba.store'), [
                'nombre' => ' jaba   verde ',
                'tara_referencial_kg' => '1.500',
            ]);

        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe un tipo de jaba con ese nombre.',
        ]);
        $this->assertDatabaseCount('tipos_jaba', 1);
    }

    public function test_update_configures_existing_zero_tare_without_changing_activation(): void
    {
        $user = $this->userWithManagementPermission();
        $crateType = TipoJaba::factory()->create([
            'nombre' => 'JABA TIPO A',
            'tara_referencial_kg' => '0.000',
            'activo' => true,
        ]);

        $response = $this->actingAs($user)->put(route('tipos-jaba.update', $crateType), [
            'nombre' => ' jaba tipo a ',
            'tara_referencial_kg' => '2.875',
            'descripcion' => 'Tara verificada',
            'activo' => '0',
        ]);

        $response
            ->assertRedirect(route('tipos-jaba.index'))
            ->assertSessionHas('status', 'Tipo de jaba actualizado correctamente.');
        $this->assertDatabaseHas('tipos_jaba', [
            'id' => $crateType->id,
            'nombre' => 'JABA TIPO A',
            'tara_referencial_kg' => '2.875',
            'descripcion' => 'Tara verificada',
            'activo' => 1,
        ]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'tara_referencial_kg',
            'valor_anterior' => '0.000',
            'valor_nuevo' => '2.875',
        ]);
    }

    public function test_edit_form_displays_tare_without_redundant_zeroes_and_keeps_it_editable(): void
    {
        $user = $this->userWithManagementPermission();
        $crateType = TipoJaba::factory()->create([
            'tara_referencial_kg' => '6.8',
        ]);

        $response = $this->actingAs($user)->get(route('tipos-jaba.edit', $crateType));
        $previousInternalErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);
        $tareInput = $document->getElementById('tara_referencial_kg');

        $response->assertSee('Editar jaba');
        $this->assertInstanceOf(DOMElement::class, $tareInput);
        $this->assertSame('6.8', $tareInput->getAttribute('value'));
        $this->assertSame('0.001', $tareInput->getAttribute('step'));
        $this->assertFalse($tareInput->hasAttribute('disabled'));
        $this->assertFalse($tareInput->hasAttribute('readonly'));
    }

    private function userWithManagementPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::query()
            ->where('codigo', 'TIPOS_JABA_GESTIONAR')
            ->firstOrFail();

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
