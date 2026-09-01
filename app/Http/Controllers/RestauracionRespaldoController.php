<?php

namespace App\Http\Controllers;

use App\Contracts\GestorRespaldos;
use App\Http\Requests\RestaurarRespaldoRequest;
use App\Models\Respaldo;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class RestauracionRespaldoController extends Controller
{
    public function create(Respaldo $respaldo): View
    {
        abort_unless($respaldo->estaRestaurable(), 409, 'El respaldo debe estar verificado antes de restaurarlo.');

        return view('respaldos.restaurar', [
            'backup' => $respaldo->load('creadoPor:id,nombres,apellidos,usuario'),
        ]);
    }

    public function store(RestaurarRespaldoRequest $request, Respaldo $respaldo, GestorRespaldos $manager): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        try {
            $manager->restaurar($respaldo, $user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'restauracion' => $exception->getMessage(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')
            ->with('status', 'Base de datos restaurada correctamente. Inicia sesión nuevamente.');
    }
}
