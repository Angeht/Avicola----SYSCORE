<?php

namespace App\Services;

use App\Contracts\GestorRespaldos;
use App\Contracts\MotorRespaldoBaseDatos;
use App\Models\ConfiguracionRespaldo;
use App\Models\Respaldo;
use App\Models\RestauracionRespaldo;
use App\Models\Usuario;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GestorRespaldosMySql implements GestorRespaldos
{
    public function __construct(private MotorRespaldoBaseDatos $motor) {}

    public function motorDisponible(): bool
    {
        return $this->motor->disponible();
    }

    public function crear(string $tipo, ?Usuario $actor = null): Respaldo
    {
        return Cache::lock('respaldos:mysql:operacion', 1800)->block(
            5,
            fn (): Respaldo => $this->crearSinBloqueo($tipo, $actor),
        );
    }

    public function verificar(Respaldo $respaldo, ?Usuario $actor = null): Respaldo
    {
        return Cache::lock('respaldos:mysql:operacion', 1800)->block(5, function () use ($actor, $respaldo): Respaldo {
            $absolutePath = $this->validarArchivo($respaldo);
            $temporaryDatabase = 'avicola_verify_'.Str::lower((string) Str::ulid());

            try {
                $this->motor->crearBaseTemporal($temporaryDatabase);
                $this->motor->restaurarVolcado($absolutePath, $temporaryDatabase);

                if ($this->motor->contarTablas($temporaryDatabase) === 0) {
                    throw new RuntimeException('La restauración de prueba no creó ninguna tabla.');
                }
            } finally {
                $this->motor->eliminarBaseTemporal($temporaryDatabase);
            }

            $respaldo->update([
                'verificado_at' => now(),
                'verificado_por' => $actor?->getKey(),
                'error' => null,
            ]);

            return $respaldo->refresh();
        });
    }

    public function restaurar(Respaldo $respaldo, Usuario $actor): RestauracionRespaldo
    {
        return Cache::lock('respaldos:mysql:operacion', 1800)->block(5, function () use ($actor, $respaldo): RestauracionRespaldo {
            if (! $respaldo->estaRestaurable()) {
                throw new RuntimeException('El respaldo debe estar verificado antes de restaurarlo.');
            }

            $sourceMetadata = $this->metadata($respaldo);
            $sourcePath = $this->validarArchivo($respaldo);
            $operation = RestauracionRespaldo::query()->create([
                'operacion_uuid' => (string) Str::uuid(),
                'respaldo_nombre' => $respaldo->nombre_archivo,
                'respaldo_ruta' => $respaldo->ruta,
                'estado' => RestauracionRespaldo::ESTADO_EN_PROCESO,
                'solicitado_por' => $actor->getKey(),
                'solicitante' => $actor->nombreCompleto(),
                'iniciado_at' => now(),
            ]);
            $operationMetadata = $operation->only([
                'operacion_uuid',
                'respaldo_nombre',
                'respaldo_ruta',
                'solicitado_por',
                'solicitante',
                'iniciado_at',
            ]);

            try {
                $preventive = $this->crearSinBloqueo(Respaldo::TIPO_PRE_RESTAURACION, $actor);
                $preventiveMetadata = $this->metadata($preventive);
                $preventivePath = $this->validarArchivo($preventive);
                $operation->update([
                    'respaldo_preventivo_nombre' => $preventive->nombre_archivo,
                    'respaldo_preventivo_ruta' => $preventive->ruta,
                ]);
            } catch (Throwable $exception) {
                $operation->update([
                    'estado' => RestauracionRespaldo::ESTADO_FALLIDA_REVERTIDA,
                    'completado_at' => now(),
                    'error' => 'No se pudo crear el respaldo preventivo: '.$this->mensajeSeguro($exception),
                ]);

                throw new RuntimeException('La restauración se canceló porque no fue posible crear la copia preventiva.', previous: $exception);
            }

            Artisan::call('down', ['--retry' => 60]);

            try {
                $this->desconectarBaseDatos();
                $this->motor->restaurarVolcado($sourcePath);
                $this->reconectarBaseDatos();

                return $this->registrarRestauracion(
                    $operationMetadata,
                    $sourceMetadata,
                    $preventiveMetadata,
                    RestauracionRespaldo::ESTADO_COMPLETADA,
                );
            } catch (Throwable $restoreException) {
                try {
                    $this->desconectarBaseDatos();
                    $this->motor->restaurarVolcado($preventivePath);
                    $this->reconectarBaseDatos();

                    $this->registrarRestauracion(
                        $operationMetadata,
                        $sourceMetadata,
                        $preventiveMetadata,
                        RestauracionRespaldo::ESTADO_FALLIDA_REVERTIDA,
                        'La restauración falló y se recuperó la copia preventiva: '.$this->mensajeSeguro($restoreException),
                    );
                } catch (Throwable $rollbackException) {
                    Log::critical('Falló la restauración de MySQL y también su reversión preventiva.', [
                        'operacion_uuid' => $operationMetadata['operacion_uuid'],
                        'restauracion' => $this->mensajeSeguro($restoreException),
                        'reversion' => $this->mensajeSeguro($rollbackException),
                    ]);

                    throw new RuntimeException(
                        'Ocurrió un fallo crítico al restaurar y al recuperar la copia preventiva. Revisa los registros del sistema.',
                        previous: $rollbackException,
                    );
                }

                throw new RuntimeException('La restauración falló; la base actual fue recuperada desde la copia preventiva.', previous: $restoreException);
            } finally {
                Artisan::call('up');
            }
        });
    }

    public function eliminar(Respaldo $respaldo, Usuario $actor): void
    {
        Cache::lock('respaldos:mysql:operacion', 300)->block(5, function () use ($actor, $respaldo): void {
            if ($respaldo->estado === Respaldo::ESTADO_EN_PROCESO) {
                throw new RuntimeException('No se puede eliminar una copia que aún está en proceso.');
            }

            $disk = $this->disk($respaldo->disco);

            if ($disk->exists($respaldo->ruta) && ! $disk->delete($respaldo->ruta)) {
                throw new RuntimeException('No fue posible eliminar el archivo del respaldo.');
            }

            $respaldo->update([
                'estado' => Respaldo::ESTADO_ELIMINADO,
                'eliminado_at' => now(),
                'eliminado_por' => $actor->getKey(),
            ]);
        });
    }

    private function crearSinBloqueo(string $tipo, ?Usuario $actor): Respaldo
    {
        if (! in_array($tipo, [Respaldo::TIPO_MANUAL, Respaldo::TIPO_AUTOMATICO, Respaldo::TIPO_PRE_RESTAURACION], true)) {
            throw new RuntimeException('El tipo de respaldo solicitado no es válido.');
        }

        if (! $this->motor->disponible()) {
            throw new RuntimeException('Las herramientas de MySQL no están disponibles en el servidor.');
        }

        $diskName = (string) config('respaldos.disk', 'local');
        $disk = $this->disk($diskName);
        $directory = trim((string) config('respaldos.directory', 'respaldos/mysql'), '/');
        $filename = sprintf(
            'avicola-%s-%s-%s.sql',
            Str::lower($tipo),
            now()->format('Ymd-His'),
            Str::lower((string) Str::ulid()),
        );
        $path = "$directory/$filename";
        $disk->makeDirectory($directory);
        $backup = Respaldo::query()->create([
            'nombre_archivo' => $filename,
            'disco' => $diskName,
            'ruta' => $path,
            'tipo' => $tipo,
            'estado' => Respaldo::ESTADO_EN_PROCESO,
            'creado_por' => $actor?->getKey(),
            'creado_at' => now(),
        ]);

        try {
            $absolutePath = $disk->path($path);
            $this->motor->crearVolcado($absolutePath);

            if (! is_file($absolutePath) || filesize($absolutePath) === false || filesize($absolutePath) === 0) {
                throw new RuntimeException('MySQL no generó un archivo de respaldo válido.');
            }

            $size = filesize($absolutePath);
            $checksum = hash_file('sha256', $absolutePath);

            if ($size === false || ! is_string($checksum)) {
                throw new RuntimeException('No fue posible calcular la integridad del respaldo.');
            }

            $backup->update([
                'estado' => Respaldo::ESTADO_COMPLETADO,
                'tamano_bytes' => $size,
                'checksum_sha256' => $checksum,
                'error' => null,
            ]);

            if ($tipo === Respaldo::TIPO_AUTOMATICO) {
                try {
                    $this->aplicarRetencion();
                } catch (Throwable $retentionException) {
                    report($retentionException);
                }
            }

            return $backup->refresh();
        } catch (Throwable $exception) {
            $disk->delete($path);
            $backup->update([
                'estado' => Respaldo::ESTADO_FALLIDO,
                'error' => $this->mensajeSeguro($exception),
            ]);

            throw new RuntimeException('No fue posible crear el respaldo de MySQL.', previous: $exception);
        }
    }

    private function validarArchivo(Respaldo $respaldo): string
    {
        if (! $respaldo->estaDisponible()) {
            throw new RuntimeException('El respaldo seleccionado no está disponible.');
        }

        $disk = $this->disk($respaldo->disco);

        if (! $disk->exists($respaldo->ruta)) {
            throw new RuntimeException('El archivo físico del respaldo no existe.');
        }

        $absolutePath = $disk->path($respaldo->ruta);
        $size = filesize($absolutePath);
        $checksum = hash_file('sha256', $absolutePath);

        if ($size === false
            || $respaldo->tamano_bytes !== $size
            || ! is_string($checksum)
            || ! hash_equals((string) $respaldo->checksum_sha256, $checksum)) {
            throw new RuntimeException('La integridad del archivo no coincide con el respaldo registrado.');
        }

        return $absolutePath;
    }

    private function aplicarRetencion(): void
    {
        $retention = ConfiguracionRespaldo::singleton()->retencion_cantidad;
        $obsolete = Respaldo::query()
            ->where('tipo', Respaldo::TIPO_AUTOMATICO)
            ->disponibles()
            ->orderByDesc('creado_at')
            ->orderByDesc('id')
            ->skip($retention)
            ->take(100)
            ->get();

        foreach ($obsolete as $backup) {
            $disk = $this->disk($backup->disco);

            if ($disk->exists($backup->ruta)) {
                $disk->delete($backup->ruta);
            }

            $backup->update([
                'estado' => Respaldo::ESTADO_ELIMINADO,
                'eliminado_at' => now(),
                'eliminado_por' => null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function metadata(Respaldo $respaldo): array
    {
        return $respaldo->only([
            'nombre_archivo',
            'disco',
            'ruta',
            'tipo',
            'estado',
            'tamano_bytes',
            'checksum_sha256',
            'creado_por',
            'creado_at',
            'verificado_at',
            'verificado_por',
            'error',
        ]);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $preventive
     */
    private function registrarRestauracion(array $operation, array $source, array $preventive, string $status, ?string $error = null): RestauracionRespaldo
    {
        $this->sincronizarRespaldo($source);
        $this->sincronizarRespaldo($preventive);
        $actorId = Usuario::query()->whereKey($operation['solicitado_por'])->exists()
            ? $operation['solicitado_por']
            : null;

        return RestauracionRespaldo::query()->updateOrCreate(
            ['operacion_uuid' => $operation['operacion_uuid']],
            [
                'respaldo_nombre' => $operation['respaldo_nombre'],
                'respaldo_ruta' => $operation['respaldo_ruta'],
                'respaldo_preventivo_nombre' => $preventive['nombre_archivo'],
                'respaldo_preventivo_ruta' => $preventive['ruta'],
                'estado' => $status,
                'solicitado_por' => $actorId,
                'solicitante' => $operation['solicitante'],
                'iniciado_at' => $operation['iniciado_at'],
                'completado_at' => now(),
                'error' => $error,
            ],
        );
    }

    /** @param array<string, mixed> $metadata */
    private function sincronizarRespaldo(array $metadata): void
    {
        $actorId = filled($metadata['creado_por'] ?? null)
            && Usuario::query()->whereKey($metadata['creado_por'])->exists()
                ? $metadata['creado_por']
                : null;

        Respaldo::query()->updateOrCreate(
            ['ruta' => $metadata['ruta']],
            [
                ...$metadata,
                'estado' => Respaldo::ESTADO_COMPLETADO,
                'creado_por' => $actorId,
                'eliminado_at' => null,
                'eliminado_por' => null,
            ],
        );
    }

    private function desconectarBaseDatos(): void
    {
        DB::disconnect((string) config('database.default'));
    }

    private function reconectarBaseDatos(): void
    {
        $connection = (string) config('database.default');
        DB::purge($connection);
        DB::reconnect($connection);
    }

    private function disk(string $name): FilesystemAdapter
    {
        $disk = Storage::disk($name);

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('El disco de respaldos no permite operaciones locales.');
        }

        return $disk;
    }

    private function mensajeSeguro(Throwable $exception): string
    {
        return str($exception->getMessage())->squish()->limit(1500)->toString();
    }
}
