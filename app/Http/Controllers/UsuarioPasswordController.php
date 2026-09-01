<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetUsuarioPasswordRequest;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioPasswordController extends Controller
{
    public function edit(Request $request, Usuario $usuario): View
    {
        $actor = $request->user();

        abort_unless($actor instanceof Usuario && $actor->puedeAdministrarUsuario($usuario), 403);

        $usuario->load('roles:id,nombre,activo');

        return view('usuarios.password', ['user' => $usuario]);
    }

    public function update(ResetUsuarioPasswordRequest $request, Usuario $usuario): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor instanceof Usuario, 403);

        DB::transaction(function () use ($actor, $request, $usuario): void {
            $lockedUser = Usuario::query()
                ->whereKey($usuario->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUser->load('roles.permisos:id');

            abort_unless($actor->puedeAdministrarUsuario($lockedUser), 403);

            $lockedUser->update([
                'password_hash' => $request->validated('password'),
            ]);

            $auditId = DB::table('auditorias')->insertGetId([
                'usuario_id' => $actor->getKey(),
                'tabla_afectada' => 'usuarios',
                'registro_id' => $lockedUser->getKey(),
                'accion' => 'UPDATE',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            DB::table('auditoria_detalles')->insert([
                'auditoria_id' => $auditId,
                'campo' => 'password_hash',
                'valor_anterior' => 'PROTEGIDA',
                'valor_nuevo' => 'RESTABLECIDA ADMINISTRATIVAMENTE',
            ]);
        }, 3);

        return to_route('usuarios.edit', $usuario)
            ->with('status', "Contraseña de {$usuario->usuario} restablecida correctamente.");
    }
}
