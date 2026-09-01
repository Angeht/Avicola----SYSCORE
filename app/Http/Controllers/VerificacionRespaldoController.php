<?php

namespace App\Http\Controllers;

use App\Contracts\GestorRespaldos;
use App\Models\Respaldo;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class VerificacionRespaldoController extends Controller
{
    public function store(Request $request, Respaldo $respaldo, GestorRespaldos $manager): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        try {
            $manager->verificar($respaldo, $user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'respaldo' => 'La restauración de prueba no pudo completarse. La base de datos actual no fue modificada.',
            ]);
        }

        return to_route('respaldos.index')
            ->with('status', "Copia {$respaldo->nombre_archivo} verificada mediante una restauración aislada.");
    }
}
