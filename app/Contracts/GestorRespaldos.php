<?php

namespace App\Contracts;

use App\Models\Respaldo;
use App\Models\RestauracionRespaldo;
use App\Models\Usuario;

interface GestorRespaldos
{
    public function motorDisponible(): bool;

    public function crear(string $tipo, ?Usuario $actor = null): Respaldo;

    public function verificar(Respaldo $respaldo, ?Usuario $actor = null): Respaldo;

    public function restaurar(Respaldo $respaldo, Usuario $actor): RestauracionRespaldo;

    public function eliminar(Respaldo $respaldo, Usuario $actor): void;
}
