<?php

namespace App\Contracts;

interface MotorRespaldoBaseDatos
{
    public function disponible(): bool;

    public function crearVolcado(string $rutaAbsoluta): void;

    public function restaurarVolcado(string $rutaAbsoluta, ?string $baseDatos = null): void;

    public function crearBaseTemporal(string $baseDatos): void;

    public function eliminarBaseTemporal(string $baseDatos): void;

    public function contarTablas(string $baseDatos): int;
}
