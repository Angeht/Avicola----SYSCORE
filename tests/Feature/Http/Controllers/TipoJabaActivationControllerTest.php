<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoJaba;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TipoJabaActivationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_user_can_deactivate_crate_type_without_deleting_history(): void
    {
        $user = $this->userWithManagementPermission();
        $crateType = TipoJaba::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('tipos-jaba.activacion.destroy', $crateType));

        $response
            ->assertRedirect(route('tipos-jaba.index'))
            ->assertSessionHas('status', 'Tipo de jaba desactivado. Su historial se mantiene disponible.');
        $this->assertModelExists($crateType);
        $this->assertDatabaseHas('tipos_jaba', ['id' => $crateType->id, 'activo' => 0]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'activo',
            'valor_anterior' => '1',
            'valor_nuevo' => 'No',
        ]);
    }

    public function test_authorized_user_can_reactivate_crate_type(): void
    {
        $user = $this->userWithManagementPermission();
        $crateType = TipoJaba::factory()->inactivo()->create();

        $response = $this->actingAs($user)
            ->post(route('tipos-jaba.activacion.store', $crateType));

        $response
            ->assertRedirect(route('tipos-jaba.index'))
            ->assertSessionHas('status', 'Tipo de jaba activado correctamente.');
        $this->assertTrue($crateType->fresh()->activo);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'activo',
            'valor_anterior' => '0',
            'valor_nuevo' => 'Sí',
        ]);
    }

    public function test_user_without_management_permission_cannot_change_activation(): void
    {
        $user = Usuario::factory()->create();
        $crateType = TipoJaba::factory()->create();

        $this->actingAs($user)
            ->delete(route('tipos-jaba.activacion.destroy', $crateType))
            ->assertForbidden();
        $this->assertTrue($crateType->fresh()->activo);
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
