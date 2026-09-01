<?php

namespace App\Services;

use App\Contracts\MotorRespaldoBaseDatos;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class MotorMySql implements MotorRespaldoBaseDatos
{
    public function disponible(): bool
    {
        return in_array(config('database.default'), ['mysql', 'mariadb'], true)
            && $this->resolverBinario('mysqldump') !== null
            && $this->resolverBinario('mysql') !== null;
    }

    public function crearVolcado(string $rutaAbsoluta): void
    {
        $dumpBinary = $this->binarioRequerido('mysqldump');

        $this->conArchivoCredenciales(function (string $credentialFile) use ($dumpBinary, $rutaAbsoluta): void {
            $this->ejecutar([
                $dumpBinary,
                "--defaults-extra-file=$credentialFile",
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--set-gtid-purged=OFF',
                '--default-character-set=utf8mb4',
                "--result-file=$rutaAbsoluta",
                $this->nombreBaseDatos(),
            ]);
        });
    }

    public function restaurarVolcado(string $rutaAbsoluta, ?string $baseDatos = null): void
    {
        if (! is_file($rutaAbsoluta)) {
            throw new RuntimeException('El archivo de respaldo no existe.');
        }

        $mysqlBinary = $this->binarioRequerido('mysql');
        $database = $baseDatos ?? $this->nombreBaseDatos();
        $this->validarNombreBaseDatos($database);
        $stream = fopen($rutaAbsoluta, 'rb');

        if ($stream === false) {
            throw new RuntimeException('No fue posible abrir el archivo de respaldo.');
        }

        try {
            $this->conArchivoCredenciales(function (string $credentialFile) use ($database, $mysqlBinary, $stream): void {
                $process = $this->nuevoProceso([
                    $mysqlBinary,
                    "--defaults-extra-file=$credentialFile",
                    '--default-character-set=utf8mb4',
                    $database,
                ]);
                $process->setInput($stream);
                $this->ejecutarProceso($process);
            });
        } finally {
            fclose($stream);
        }
    }

    public function crearBaseTemporal(string $baseDatos): void
    {
        $this->validarNombreBaseDatos($baseDatos);
        $this->ejecutarConsulta("CREATE DATABASE `$baseDatos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function eliminarBaseTemporal(string $baseDatos): void
    {
        $this->validarNombreBaseDatos($baseDatos);
        $this->ejecutarConsulta("DROP DATABASE IF EXISTS `$baseDatos`");
    }

    public function contarTablas(string $baseDatos): int
    {
        $this->validarNombreBaseDatos($baseDatos);
        $mysqlBinary = $this->binarioRequerido('mysql');

        return $this->conArchivoCredenciales(function (string $credentialFile) use ($baseDatos, $mysqlBinary): int {
            $process = $this->nuevoProceso([
                $mysqlBinary,
                "--defaults-extra-file=$credentialFile",
                '--batch',
                '--skip-column-names',
                "--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$baseDatos'",
            ]);
            $this->ejecutarProceso($process);

            return (int) trim($process->getOutput());
        });
    }

    private function ejecutarConsulta(string $sql): void
    {
        $mysqlBinary = $this->binarioRequerido('mysql');

        $this->conArchivoCredenciales(function (string $credentialFile) use ($mysqlBinary, $sql): void {
            $this->ejecutar([
                $mysqlBinary,
                "--defaults-extra-file=$credentialFile",
                "--execute=$sql",
            ]);
        });
    }

    /** @param list<string> $command */
    private function ejecutar(array $command): void
    {
        $this->ejecutarProceso($this->nuevoProceso($command));
    }

    /** @param list<string> $command */
    private function nuevoProceso(array $command): Process
    {
        return new Process(
            $command,
            base_path(),
            timeout: (float) config('respaldos.timeout_seconds', 900),
        );
    }

    private function ejecutarProceso(Process $process): void
    {
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'El proceso MySQL terminó con un error desconocido.';

            throw new RuntimeException(str($error)->limit(1500)->toString());
        }
    }

    /** @template T @param callable(string): T $callback @return T */
    private function conArchivoCredenciales(callable $callback): mixed
    {
        $directory = storage_path('framework/cache');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar el directorio temporal.');
        }

        $path = tempnam($directory, 'avicola-mysql-');

        if ($path === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de conexión.');
        }

        try {
            if (file_put_contents($path, $this->contenidoCredenciales()) === false) {
                throw new RuntimeException('No fue posible preparar las credenciales temporales.');
            }

            return $callback($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function contenidoCredenciales(): string
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! is_array($connection)) {
            throw new RuntimeException('La conexión de base de datos no está configurada.');
        }

        $lines = [
            '[client]',
            'host='.$this->valorOpcion((string) ($connection['host'] ?? '127.0.0.1')),
            'port='.(int) ($connection['port'] ?? 3306),
            'user='.$this->valorOpcion((string) ($connection['username'] ?? 'root')),
            'password='.$this->valorOpcion((string) ($connection['password'] ?? '')),
            'default-character-set=utf8mb4',
        ];

        if (filled($connection['unix_socket'] ?? null)) {
            $lines[] = 'socket='.$this->valorOpcion((string) $connection['unix_socket']);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function valorOpcion(string $value): string
    {
        return '"'.str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\\"', '\\r', '\\n'],
            $value,
        ).'"';
    }

    private function nombreBaseDatos(): string
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $this->validarNombreBaseDatos($database);

        return $database;
    }

    private function validarNombreBaseDatos(string $database): void
    {
        if ($database === '' || mb_strlen($database) > 64 || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new RuntimeException('El nombre de la base de datos no es válido para la operación.');
        }
    }

    private function binarioRequerido(string $name): string
    {
        return $this->resolverBinario($name)
            ?? throw new RuntimeException("No se encontró el ejecutable $name de MySQL.");
    }

    private function resolverBinario(string $name): ?string
    {
        $configured = config($name === 'mysqldump'
            ? 'respaldos.dump_binary'
            : 'respaldos.mysql_binary');

        if (is_string($configured) && is_file($configured)) {
            return $configured;
        }

        $extension = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $laragonRoot = config('respaldos.laragon_mysql_root');

        if (is_string($laragonRoot) && is_dir($laragonRoot)) {
            $candidates = glob(rtrim($laragonRoot, '\\/').'/*/bin/'.$name.$extension) ?: [];
            natsort($candidates);

            if ($candidates !== []) {
                $candidates = array_values($candidates);

                return $candidates[array_key_last($candidates)];
            }
        }

        return (new ExecutableFinder)->find($name);
    }
}
