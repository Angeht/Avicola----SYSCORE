<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioPasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $target = Usuario::factory()->create();

        $this->get(route('usuarios.password.edit', $target))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_management_permission_is_forbidden(): void
    {
        $actor = Usuario::factory()->create();
        $target = Usuario::factory()->create();

        $this->actingAs($actor)
            ->get(route('usuarios.password.edit', $target))
            ->assertForbidden();
    }

    public function test_authorized_user_can_render_administrative_password_screen_safely(): void
    {
        $actor = $this->userWithPermission();
        $dangerousName = 'Cuenta <script>alert(1)</script>';
        $target = Usuario::factory()->create(['nombres' => $dangerousName]);

        $this->actingAs($actor)
            ->get(route('usuarios.password.edit', $target))
            ->assertOk()
            ->assertSee($dangerousName)
            ->assertDontSee($dangerousName, false);
    }

    public function test_authorized_user_can_reset_password_and_audit_does_not_store_secrets(): void
    {
        $actor = $this->userWithPermission();
        $target = Usuario::factory()->create([
            'usuario' => 'operador',
            'password_hash' => 'Clave#Anterior2026',
        ]);

        $response = $this->actingAs($actor)->put(route('usuarios.password.update', $target), [
            'password' => 'Nueva#ClaveSegura2026',
            'password_confirmation' => 'Nueva#ClaveSegura2026',
        ]);

        $response
            ->assertRedirect(route('usuarios.edit', $target))
            ->assertSessionHas('status', 'Contraseña de operador restablecida correctamente.');
        $this->assertTrue(Hash::check('Nueva#ClaveSegura2026', $target->fresh()->password_hash));
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'password_hash',
            'valor_anterior' => 'PROTEGIDA',
            'valor_nuevo' => 'RESTABLECIDA ADMINISTRATIVAMENTE',
        ]);
        $this->assertDatabaseMissing('auditoria_detalles', ['valor_nuevo' => 'Nueva#ClaveSegura2026']);
    }

    public function test_password_confirmation_is_required_and_existing_password_is_preserved(): void
    {
        $actor = $this->userWithPermission();
        $target = Usuario::factory()->create(['password_hash' => 'Clave#Anterior2026']);

        $response = $this->actingAs($actor)->put(route('usuarios.password.update', $target), [
            'password' => 'Nueva#ClaveSegura2026',
            'password_confirmation' => 'Otra#ClaveSegura2026',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'La confirmación de la contraseña no coincide.',
        ]);
        $this->assertTrue(Hash::check('Clave#Anterior2026', $target->fresh()->password_hash));
        $this->assertDatabaseCount('auditorias', 0);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'USUARIOS_GESTIONAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
