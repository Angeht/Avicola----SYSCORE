<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RolActivationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_administrator_can_deactivate_role_and_permissions_stop_applying(): void
    {
        $administratorRole = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $administrator = Usuario::factory()->create();
        $administrator->roles()->attach($administratorRole);
        $permission = Permiso::factory()->create(['codigo' => 'REPORTES_VER']);
        $role = Rol::factory()->create(['nombre' => 'CONSULTA']);
        $role->permisos()->attach($permission);
        $assignedUser = Usuario::factory()->create();
        $assignedUser->roles()->attach($role);

        $this->assertTrue($assignedUser->tienePermiso('REPORTES_VER'));

        $response = $this->actingAs($administrator)
            ->delete(route('roles.activacion.destroy', $role));

        $response
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('status', 'Rol desactivado. Sus asignaciones se conservaron sin conceder permisos.');
        $this->assertFalse($role->fresh()->activo);
        $this->assertFalse($assignedUser->fresh()->tienePermiso('REPORTES_VER'));
        $this->assertDatabaseHas('usuario_rol', ['usuario_id' => $assignedUser->id, 'rol_id' => $role->id]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'activo',
            'valor_anterior' => '1',
            'valor_nuevo' => '0',
        ]);
    }

    public function test_authorized_user_can_reactivate_role(): void
    {
        $actor = $this->userWithPermission();
        $role = Rol::factory()->inactivo()->create();

        $response = $this->actingAs($actor)
            ->post(route('roles.activacion.store', $role));

        $response
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('status', 'Rol activado correctamente.');
        $this->assertTrue($role->fresh()->activo);
    }

    public function test_administrator_role_cannot_be_deactivated(): void
    {
        $administratorRole = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $administrator = Usuario::factory()->create();
        $administrator->roles()->attach($administratorRole);

        $response = $this->actingAs($administrator)
            ->delete(route('roles.activacion.destroy', $administratorRole));

        $response->assertSessionHasErrors([
            'rol' => 'El rol ADMINISTRADOR es estructural y no puede desactivarse.',
        ]);
        $this->assertTrue($administratorRole->fresh()->activo);
    }

    public function test_non_administrator_cannot_deactivate_role_that_authorizes_current_session(): void
    {
        $actor = $this->userWithPermission();
        $role = $actor->roles()->firstOrFail();

        $response = $this->actingAs($actor)
            ->delete(route('roles.activacion.destroy', $role));

        $response->assertSessionHasErrors([
            'rol' => 'No puedes desactivar el rol que autoriza tu sesión actual.',
        ]);
        $this->assertTrue($role->fresh()->activo);
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
