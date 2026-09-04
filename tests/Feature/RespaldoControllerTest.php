<?php

namespace Tests\Feature;

use App\Contracts\GestorRespaldos;
use App\Models\Permiso;
use App\Models\Respaldo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class RespaldoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('respaldos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_management_permission_is_forbidden(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->get(route('respaldos.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_backup_management_with_escaped_content(): void
    {
        $user = $this->userWithManagementPermission();
        $dangerousError = "Fallo <script>alert('xss')</script>";
        Respaldo::factory()->fallido()->create(['error' => $dangerousError]);
        $this->mock(GestorRespaldos::class, function (MockInterface $mock): void {
            $mock->shouldReceive('motorDisponible')->once()->andReturnTrue();
        });
        $response = $this->actingAs($user)->get(route('respaldos.index'));

        $response
            ->assertOk()
            ->assertSee('Respaldos de MySQL')
            ->assertSee('Crear copia ahora')
            ->assertSee('Restauración segura')
            ->assertSee($dangerousError)
            ->assertDontSee($dangerousError, false)
            ->assertDontSee('Configuración automática')
            ->assertDontSee('Programador del sistema');
    }

    public function test_authorized_user_can_create_a_manual_backup(): void
    {
        $user = $this->userWithManagementPermission();
        $backup = Respaldo::factory()->create(['creado_por' => $user]);
        $this->mock(GestorRespaldos::class, function (MockInterface $mock) use ($backup, $user): void {
            $mock->shouldReceive('crear')
                ->once()
                ->with(Respaldo::TIPO_MANUAL, $user)
                ->andReturn($backup);
        });

        $this->actingAs($user)
            ->post(route('respaldos.store'))
            ->assertRedirect(route('respaldos.index'))
            ->assertSessionHas('status', "Copia {$backup->nombre_archivo} creada correctamente.");
    }

    public function test_automatic_backup_configuration_and_scheduler_endpoints_are_not_available(): void
    {
        $user = $this->userWithManagementPermission();

        $this->actingAs($user)
            ->put('/respaldos/configuracion')
            ->assertMethodNotAllowed();
        $this->actingAs($user)
            ->post('/respaldos/tarea-programada')
            ->assertMethodNotAllowed();
    }

    public function test_authorized_user_can_download_only_an_available_private_backup(): void
    {
        Storage::fake('local');
        config()->set('respaldos.disk', 'local');
        $user = $this->userWithManagementPermission();
        $contents = 'CREATE TABLE prueba (id BIGINT);';
        $path = 'respaldos/mysql/avicola-prueba.sql';
        Storage::disk('local')->put($path, $contents);
        $backup = Respaldo::factory()->create([
            'nombre_archivo' => 'avicola-prueba.sql',
            'ruta' => $path,
            'tamano_bytes' => strlen($contents),
            'checksum_sha256' => hash('sha256', $contents),
        ]);

        $this->actingAs($user)
            ->get(route('respaldos.download', $backup))
            ->assertOk()
            ->assertDownload('avicola-prueba.sql')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $backup->update(['estado' => Respaldo::ESTADO_ELIMINADO, 'eliminado_at' => now()]);

        $this->actingAs($user)
            ->get(route('respaldos.download', $backup))
            ->assertNotFound();
    }

    private function userWithManagementPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::query()->firstOrCreate(
            ['codigo' => 'RESPALDOS_GESTIONAR'],
            ['nombre' => 'Gestionar respaldos'],
        );

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
