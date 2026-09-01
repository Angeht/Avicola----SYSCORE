<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_user_with_report_permission_can_view_operational_dashboard(): void
    {
        $user = Usuario::factory()->create();
        $this->grantPermission($user, 'REPORTES_VER');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Resumen de planta')
            ->assertSee('Saldo de mercadería')
            ->assertSee('Todo bajo control');
    }

    public function test_user_without_report_permission_cannot_view_dashboard(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    private function grantPermission(Usuario $user, string $code): void
    {
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => $code]);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);
    }
}
