<?php

namespace App\Console\Commands;

use App\Contracts\GestorRespaldos;
use App\Models\ConfiguracionRespaldo;
use App\Models\Respaldo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('respaldos:crear {--automatico : Ejecutar solo cuando corresponda según la configuración guardada}')]
#[Description('Crea una copia segura de la base de datos MySQL de la avícola')]
class CrearRespaldoCommand extends Command
{
    public function handle(GestorRespaldos $manager): int
    {
        $automatic = (bool) $this->option('automatico');
        $configuration = ConfiguracionRespaldo::singleton();

        if ($automatic && (! $configuration->activo || ! $this->correspondeEjecutar($configuration))) {
            $this->components->info('No corresponde crear una copia en este minuto.');

            return self::SUCCESS;
        }

        try {
            $backup = $manager->crear(
                $automatic ? Respaldo::TIPO_AUTOMATICO : Respaldo::TIPO_MANUAL,
            );

            if ($automatic && $configuration->verificar_automaticamente) {
                try {
                    $manager->verificar($backup);
                } catch (Throwable $verificationException) {
                    $backup->update([
                        'error' => 'La copia fue creada, pero su restauración de prueba falló.',
                    ]);

                    throw $verificationException;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('No fue posible completar el respaldo: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Respaldo {$backup->nombre_archivo} creado correctamente.");

        return self::SUCCESS;
    }

    private function correspondeEjecutar(ConfiguracionRespaldo $configuration): bool
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        [$hour, $minute] = array_map('intval', explode(':', $configuration->hora));
        $scheduledAt = match ($configuration->frecuencia) {
            ConfiguracionRespaldo::FRECUENCIA_SEMANAL => $now
                ->startOfWeek(CarbonInterface::MONDAY)
                ->addDays(($configuration->dia_semana ?? 1) - 1)
                ->setTime($hour, $minute),
            ConfiguracionRespaldo::FRECUENCIA_MENSUAL => $now
                ->startOfMonth()
                ->addDays(($configuration->dia_mes ?? 1) - 1)
                ->setTime($hour, $minute),
            default => $now->startOfDay()->setTime($hour, $minute),
        };

        if ($scheduledAt->isFuture()) {
            $scheduledAt = match ($configuration->frecuencia) {
                ConfiguracionRespaldo::FRECUENCIA_SEMANAL => $scheduledAt->subWeek(),
                ConfiguracionRespaldo::FRECUENCIA_MENSUAL => $scheduledAt->subMonthNoOverflow(),
                default => $scheduledAt->subDay(),
            };
        }

        $lastAttempt = Respaldo::query()
            ->where('tipo', Respaldo::TIPO_AUTOMATICO)
            ->latest('creado_at')
            ->value('creado_at');

        return $lastAttempt === null
            || CarbonImmutable::parse($lastAttempt)->isBefore($scheduledAt);
    }
}
