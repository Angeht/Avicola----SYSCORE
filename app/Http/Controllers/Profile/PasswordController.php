<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('profile.password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof Usuario, 403);

        $user->update([
            'password_hash' => $request->validated('password'),
        ]);

        $audit = Auditoria::query()->create([
            'usuario_id' => $user->getKey(),
            'tabla_afectada' => 'usuarios',
            'registro_id' => $user->getKey(),
            'accion' => 'UPDATE',
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
        $audit->detalles()->create([
            'campo' => 'password_hash',
            'valor_anterior' => 'PROTEGIDA',
            'valor_nuevo' => 'ACTUALIZADA POR EL USUARIO',
        ]);

        return back()->with('status', 'Tu contraseña fue actualizada correctamente.');
    }
}
