<?php

namespace App\Http\Controllers;

use App\Services\TareaProgramadaWindows;
use Illuminate\Http\RedirectResponse;
use Throwable;

class TareaProgramadaRespaldoController extends Controller
{
    public function store(TareaProgramadaWindows $scheduledTask): RedirectResponse
    {
        try {
            $scheduledTask->instalar();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'tarea_programada' => 'Windows no permitió instalar la tarea. Ejecuta Laragon con permisos suficientes y vuelve a intentarlo.',
            ]);
        }

        return to_route('respaldos.index')
            ->with('status', 'Tarea de Windows instalada. Laravel revisará cada minuto si corresponde crear una copia.');
    }
}
