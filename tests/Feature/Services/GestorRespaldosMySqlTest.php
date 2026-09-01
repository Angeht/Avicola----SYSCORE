<?php

namespace Tests\Feature\Services;

use App\Contracts\MotorRespaldoBaseDatos;
use App\Models\Respaldo;
use App\Models\Usuario;
use App\Services\GestorRespaldosMySql;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class GestorRespaldosMySqlTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('respaldos.disk', 'local');
        config()->set('respaldos.directory', 'respaldos/mysql');
    }

    public function test_creates_private_dump_with_size_and_sha256_integrity(): void
    {
        $actor = Usuario::factory()->create();
        $contents = "CREATE TABLE prueba (id BIGINT);\n";
        $engine = $this->mock(MotorRespaldoBaseDatos::class, function (MockInterface $mock) use ($contents): void {
            $mock->shouldReceive('disponible')->once()->andReturnTrue();
            $mock->shouldReceive('crearVolcado')
                ->once()
                ->andReturnUsing(function (string $absolutePath) use ($contents): void {
                    file_put_contents($absolutePath, $contents);
                });
        });

        $backup = (new GestorRespaldosMySql($engine))->crear(Respaldo::TIPO_MANUAL, $actor);

        $this->assertSame(Respaldo::ESTADO_COMPLETADO, $backup->estado);
        $this->assertSame(strlen($contents), $backup->tamano_bytes);
        $this->assertSame(hash('sha256', $contents), $backup->checksum_sha256);
        $this->assertSame($actor->id, $backup->creado_por);
        Storage::disk('local')->assertExists($backup->ruta);
    }

    public function test_verifies_backup_by_restoring_it_into_an_isolated_database(): void
    {
        $actor = Usuario::factory()->create();
        $contents = 'CREATE TABLE prueba (id BIGINT);';
        $path = 'respaldos/mysql/verificable.sql';
        Storage::disk('local')->put($path, $contents);
        $backup = Respaldo::factory()->create([
            'ruta' => $path,
            'tamano_bytes' => strlen($contents),
            'checksum_sha256' => hash('sha256', $contents),
        ]);
        $engine = $this->mock(MotorRespaldoBaseDatos::class, function (MockInterface $mock): void {
            $mock->shouldReceive('crearBaseTemporal')
                ->once()
                ->withArgs(fn (string $database): bool => str_starts_with($database, 'avicola_verify_'));
            $mock->shouldReceive('restaurarVolcado')
                ->once()
                ->withArgs(fn (string $path, string $database): bool => is_file($path)
                    && str_starts_with($database, 'avicola_verify_'));
            $mock->shouldReceive('contarTablas')
                ->once()
                ->withArgs(fn (string $database): bool => str_starts_with($database, 'avicola_verify_'))
                ->andReturn(3);
            $mock->shouldReceive('eliminarBaseTemporal')
                ->once()
                ->withArgs(fn (string $database): bool => str_starts_with($database, 'avicola_verify_'));
        });

        $verified = (new GestorRespaldosMySql($engine))->verificar($backup, $actor);

        $this->assertNotNull($verified->verificado_at);
        $this->assertSame($actor->id, $verified->verificado_por);
        $this->assertNull($verified->error);
    }

    public function test_rejects_a_tampered_backup_before_calling_mysql(): void
    {
        $original = 'CREATE TABLE original (id BIGINT);';
        $path = 'respaldos/mysql/adulterado.sql';
        Storage::disk('local')->put($path, $original);
        $backup = Respaldo::factory()->create([
            'ruta' => $path,
            'tamano_bytes' => strlen($original),
            'checksum_sha256' => hash('sha256', $original),
        ]);
        Storage::disk('local')->put($path, 'DROP DATABASE avicola;');
        $engine = $this->mock(MotorRespaldoBaseDatos::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('crearBaseTemporal');
            $mock->shouldNotReceive('restaurarVolcado');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La integridad del archivo no coincide');

        (new GestorRespaldosMySql($engine))->verificar($backup);
    }
}
