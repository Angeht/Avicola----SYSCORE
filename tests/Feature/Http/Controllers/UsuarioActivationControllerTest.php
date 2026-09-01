<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UsuarioActivationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_without_management_permission_cannot_change_account_state(): void
    {
        $actor = Usuario::factory()->create();
        $target = Usuario::factory()->create();

        $this->actingAs($actor)
            ->delete(route('usuarios.activacion.destroy', $target))
            ->assertForbidden();

        $this->assertTrue($target->fresh()->activo);
    }

    public function test_authorized_user_can_deactivate_another_account_without_deleting_it(): void
    {
        $actor = $this->userWithPermission();
        $target = Usuario::factory()->create();

        $response = $this->actingAs($actor)
            ->delete(route('usuarios.activacion.destroy', $target));

        $response
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('status', 'Usuario desactivado. Su historial se mantiene disponible.');
        $this->assertModelExists($target);
        $this->assertFalse($target->fresh()->activo);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'activo',
            'valor_anterior' => '1',
            'valor_nuevo' => '0',
        ]);
    }

    public function test_user_cannot_deactivate_own_account(): void
    {
        $actor = $this->userWithPermission();

        $response = $this->actingAs($actor)
            ->delete(route('usuarios.activacion.destroy', $actor));

        $response->assertSessionHasErrors([
            'usuario' => 'No puedes desactivar tu propia cuenta.',
        ]);
        $this->assertTrue($actor->fresh()->activo);
    }

    public function test_non_administrator_cannot_deactivate_total_administrator(): void
    {
        $actor = $this->userWithPermission();
        $administratorRole = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $administrator = Usuario::factory()->create();
        $administrator->roles()->attach($administratorRole);

        $response = $this->actingAs($actor)
            ->delete(route('usuarios.activacion.destroy', $administrator));

        $response->assertForbidden();
        $this->assertTrue($administrator->fresh()->activo);
    }

    public function test_authorized_user_can_reactivate_an_inactive_account(): void
    {
        $actor = $this->userWithPermission();
        $target = Usuario::factory()->inactivo()->create();

        $response = $this->actingAs($actor)
            ->post(route('usuarios.activacion.store', $target));

        $response
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('status', 'Usuario activado correctamente.');
        $this->assertTrue($target->fresh()->activo);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'activo',
            'valor_anterior' => '0',
            'valor_nuevo' => '1',
        ]);
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
