<?php

namespace Tests\Feature;

use App\Contracts\GestorRespaldos;
use App\Models\Permiso;
use App\Models\Respaldo;
use App\Models\RestauracionRespaldo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RestauracionRespaldoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unverified_backup_cannot_open_the_restoration_screen(): void
    {
        $user = $this->userWithManagementPermission();
        $backup = Respaldo::factory()->create();

        $this->actingAs($user)
            ->get(route('respaldos.restauracion.create', $backup))
            ->assertStatus(409);
    }

    public function test_restoration_requires_current_password_and_exact_confirmation(): void
    {
        $user = $this->userWithManagementPermission();
        $backup = Respaldo::factory()->verificado($user)->create();
        $this->mock(GestorRespaldos::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('restaurar');
        });

        $this->actingAs($user)
            ->from(route('respaldos.restauracion.create', $backup))
            ->post(route('respaldos.restauracion.store', $backup), [
                'current_password' => 'incorrecta',
                'confirmacion' => 'CANCELAR',
            ])
            ->assertRedirect(route('respaldos.restauracion.create', $backup))
            ->assertSessionHasErrors(['current_password', 'confirmacion']);
    }

    public function test_verified_backup_can_be_restored_after_reauthentication(): void
    {
        $user = $this->userWithManagementPermission();
        $backup = Respaldo::factory()->verificado($user)->create();
        $restoration = RestauracionRespaldo::factory()->create(['solicitado_por' => $user]);
        $this->mock(GestorRespaldos::class, function (MockInterface $mock) use ($backup, $restoration, $user): void {
            $mock->shouldReceive('restaurar')
                ->once()
                ->withArgs(fn (Respaldo $receivedBackup, Usuario $receivedUser): bool => $receivedBackup->is($backup)
                    && $receivedUser->is($user))
                ->andReturn($restoration);
        });

        $response = $this->actingAs($user)
            ->post(route('respaldos.restauracion.store', $backup), [
                'current_password' => 'password',
                'confirmacion' => ' restaurar ',
            ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Base de datos restaurada correctamente. Inicia sesión nuevamente.');
        $this->assertGuest();
    }

    public function test_safe_restoration_failure_returns_an_error_without_logging_the_user_out(): void
    {
        $user = $this->userWithManagementPermission();
        $backup = Respaldo::factory()->verificado($user)->create();
        $this->mock(GestorRespaldos::class, function (MockInterface $mock): void {
            $mock->shouldReceive('restaurar')
                ->once()
                ->andThrow(new RuntimeException('La restauración falló; se recuperó la copia preventiva.'));
        });

        $this->actingAs($user)
            ->from(route('respaldos.restauracion.create', $backup))
            ->post(route('respaldos.restauracion.store', $backup), [
                'current_password' => 'password',
                'confirmacion' => 'RESTAURAR',
            ])
            ->assertRedirect(route('respaldos.restauracion.create', $backup))
            ->assertSessionHasErrors([
                'restauracion' => 'La restauración falló; se recuperó la copia preventiva.',
            ]);
        $this->assertAuthenticatedAs($user);
    }

    private function userWithManagementPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $permission = Permiso::query()->firstOrCreate(
            ['codigo' => 'RESPALDOS_GESTIONAR'],
            ['nombre' => 'Gestionar respaldos'],
        );

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
