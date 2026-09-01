<?php

namespace Tests\Feature;

use App\Contracts\GestorRespaldos;
use App\Models\ConfiguracionRespaldo;
use App\Models\Permiso;
use App\Models\Respaldo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\TareaProgramadaWindows;
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
        $this->mock(TareaProgramadaWindows::class, function (MockInterface $mock): void {
            $mock->shouldReceive('esCompatible')->once()->andReturnTrue();
            $mock->shouldReceive('estaInstalada')->once()->andReturnFalse();
        });

        $response = $this->actingAs($user)->get(route('respaldos.index'));

        $response
            ->assertOk()
            ->assertSee('Respaldos de MySQL')
            ->assertSee($dangerousError)
            ->assertDontSee($dangerousError, false)
            ->assertSee('Instalación pendiente');
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

    public function test_configuration_is_normalized_updated_and_audited(): void
    {
        $user = $this->userWithManagementPermission();

        $response = $this->actingAs($user)->put(route('respaldos.configuracion.update'), [
            'activo' => '1',
            'frecuencia' => ' mensual ',
            'hora' => '03:45',
            'dia_semana' => '6',
            'dia_mes' => '15',
            'retencion_cantidad' => '30',
            'verificar_automaticamente' => '1',
        ]);

        $response
            ->assertRedirect(route('respaldos.index'))
            ->assertSessionHas('status', 'Configuración de respaldos actualizada correctamente.');
        $this->assertDatabaseHas('configuracion_respaldos', [
            'id' => 1,
            'activo' => 1,
            'frecuencia' => ConfiguracionRespaldo::FRECUENCIA_MENSUAL,
            'hora' => '03:45:00',
            'dia_semana' => null,
            'dia_mes' => 15,
            'retencion_cantidad' => 30,
            'verificar_automaticamente' => 1,
            'actualizado_por' => $user->id,
        ]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'configuracion_respaldos',
            'registro_id' => 1,
            'accion' => 'UPDATE',
        ]);
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
