<?php

namespace App\Http\Controllers;

use App\Models\TipoJaba;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TipoJabaActivationController extends Controller
{
    public function store(Request $request, TipoJaba $tipoJaba): RedirectResponse
    {
        $this->authorizeActor($request);

        if (! $tipoJaba->activo) {
            $tipoJaba->update(['activo' => true]);
        }

        return to_route('tipos-jaba.index')
            ->with('status', 'Tipo de jaba activado correctamente.');
    }

    public function destroy(Request $request, TipoJaba $tipoJaba): RedirectResponse
    {
        $this->authorizeActor($request);

        if ($tipoJaba->activo) {
            $tipoJaba->update(['activo' => false]);
        }

        return to_route('tipos-jaba.index')
            ->with('status', 'Tipo de jaba desactivado. Su historial se mantiene disponible.');
    }

    private function authorizeActor(Request $request): void
    {
        $actor = $request->user();

        abort_unless($actor instanceof Usuario && $actor->tienePermiso('TIPOS_JABA_GESTIONAR'), 403);
    }
}
