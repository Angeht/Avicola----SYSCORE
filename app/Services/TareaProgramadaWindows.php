<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class TareaProgramadaWindows
{
    public function esCompatible(): bool
    {
        return PHP_OS_FAMILY === 'Windows'
            && $this->ejecutableTareas() !== null;
    }

    public function estaInstalada(): bool
    {
        if (! $this->esCompatible()) {
            return false;
        }

        $process = new Process([
            $this->ejecutableTareas(),
            '/Query',
            '/TN',
            $this->nombreTarea(),
        ]);
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful();
    }

    public function instalar(): void
    {
        $taskExecutable = $this->ejecutableTareas();

        if ($taskExecutable === null) {
            throw new RuntimeException('El Programador de tareas de Windows no está disponible.');
        }

        $php = $this->ejecutablePhp();
        $artisan = base_path('artisan');
        $taskCommand = sprintf('"%s" "%s" schedule:run', $php, $artisan);
        $process = new Process([
            $taskExecutable,
            '/Create',
            '/F',
            '/SC',
            'MINUTE',
            '/MO',
            '1',
            '/RL',
            'LIMITED',
            '/TN',
            $this->nombreTarea(),
            '/TR',
            $taskCommand,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException(str($error)->squish()->limit(1000)->toString());
        }
    }

    private function ejecutablePhp(): string
    {
        $candidate = PHP_BINARY;

        if (str_contains(mb_strtolower(basename($candidate)), 'php-cgi')) {
            $cliCandidate = dirname($candidate).DIRECTORY_SEPARATOR.'php.exe';

            if (is_file($cliCandidate)) {
                return $cliCandidate;
            }
        }

        if (is_file($candidate)) {
            return $candidate;
        }

        return (new ExecutableFinder)->find('php')
            ?? throw new RuntimeException('No se encontró el ejecutable PHP para la tarea programada.');
    }

    private function ejecutableTareas(): ?string
    {
        return (new ExecutableFinder)->find('schtasks');
    }

    private function nombreTarea(): string
    {
        return (string) config('respaldos.scheduler_task_name', 'Avicola Laravel Scheduler');
    }
}
