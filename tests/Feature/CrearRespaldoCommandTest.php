<?php

namespace Tests\Feature;

use App\Contracts\GestorRespaldos;
use App\Models\ConfiguracionRespaldo;
use App\Models\Respaldo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CrearRespaldoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_disabled_automatic_configuration_does_not_create_a_backup(): void
    {
        ConfiguracionRespaldo::singleton()->update(['activo' => false]);
        $this->mock(GestorRespaldos::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('crear');
            $mock->shouldNotReceive('verificar');
        });

        $this->artisan('respaldos:crear', ['--automatico' => true])
            ->expectsOutputToContain('No corresponde crear una copia')
            ->assertExitCode(0);
    }

    public function test_due_automatic_backup_is_created_and_verified(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 03:00:00');
        ConfiguracionRespaldo::singleton()->update([
            'activo' => true,
            'frecuencia' => ConfiguracionRespaldo::FRECUENCIA_DIARIA,
            'hora' => '02:00:00',
            'verificar_automaticamente' => true,
        ]);
        $backup = Respaldo::factory()->automatico()->make([
            'creado_at' => now(),
        ]);
        $this->mock(GestorRespaldos::class, function (MockInterface $mock) use ($backup): void {
            $mock->shouldReceive('crear')
                ->once()
                ->with(Respaldo::TIPO_AUTOMATICO)
                ->andReturn($backup);
            $mock->shouldReceive('verificar')
                ->once()
                ->with($backup)
                ->andReturn($backup);
        });

        $this->artisan('respaldos:crear', ['--automatico' => true])
            ->expectsOutputToContain('creado correctamente')
            ->assertExitCode(0);
    }

    public function test_automatic_backup_is_not_duplicated_after_the_scheduled_time(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 03:00:00');
        ConfiguracionRespaldo::singleton()->update([
            'activo' => true,
            'frecuencia' => ConfiguracionRespaldo::FRECUENCIA_DIARIA,
            'hora' => '02:00:00',
        ]);
        Respaldo::factory()->automatico()->create([
            'creado_at' => CarbonImmutable::parse('2026-08-29 02:30:00'),
        ]);
        $this->mock(GestorRespaldos::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('crear');
            $mock->shouldNotReceive('verificar');
        });

        $this->artisan('respaldos:crear', ['--automatico' => true])
            ->expectsOutputToContain('No corresponde crear una copia')
            ->assertExitCode(0);
    }
}
