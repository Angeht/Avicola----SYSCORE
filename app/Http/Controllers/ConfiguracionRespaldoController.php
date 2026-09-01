<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConfiguracionRespaldoRequest;
use App\Models\ConfiguracionRespaldo;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;

class ConfiguracionRespaldoController extends Controller
{
    public function update(UpdateConfiguracionRespaldoRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();
        $frequency = $validated['frecuencia'];

        ConfiguracionRespaldo::singleton()->update([
            'activo' => $validated['activo'],
            'frecuencia' => $frequency,
            'hora' => $validated['hora'].':00',
            'dia_semana' => $frequency === ConfiguracionRespaldo::FRECUENCIA_SEMANAL
                ? $validated['dia_semana']
                : null,
            'dia_mes' => $frequency === ConfiguracionRespaldo::FRECUENCIA_MENSUAL
                ? $validated['dia_mes']
                : null,
            'retencion_cantidad' => $validated['retencion_cantidad'],
            'verificar_automaticamente' => $validated['verificar_automaticamente'],
            'actualizado_por' => $user->getKey(),
        ]);

        return to_route('respaldos.index')
            ->with('status', 'Configuración de respaldos actualizada correctamente.');
    }
}
