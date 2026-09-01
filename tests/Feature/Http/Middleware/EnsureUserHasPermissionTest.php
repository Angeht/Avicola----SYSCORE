<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserHasPermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'permission:VENTAS_REGISTRAR,REPORTES_VER'])
            ->get('/prueba-permisos', fn (): string => 'Autorizado');
    }

    public function test_user_is_authorized_when_any_required_permission_is_assigned(): void
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'REPORTES_VER']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/prueba-permisos')
            ->assertOk()
            ->assertSee('Autorizado');
    }

    public function test_administrator_role_bypasses_individual_permissions(): void
    {
        $user = Usuario::factory()->create();
        $administrator = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);

        $user->roles()->attach($administrator);

        $this->actingAs($user)
            ->get('/prueba-permisos')
            ->assertOk()
            ->assertSee('Autorizado');
    }
}
