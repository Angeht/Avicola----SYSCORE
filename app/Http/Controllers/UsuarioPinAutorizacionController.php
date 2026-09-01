<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarPinAutorizacionUsuarioRequest;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioPinAutorizacionController extends Controller
{
    public function edit(Request $request, Usuario $usuario): View
    {
        $actor = $request->user();

        abort_unless($actor instanceof Usuario
            && $actor->puedeAdministrarUsuario($usuario)
            && $usuario->esAdministrador(), 403);

        $usuario->load('roles:id,nombre,activo');

        return view('usuarios.pin-autorizacion', ['user' => $usuario]);
    }

    public function update(GuardarPinAutorizacionUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof Usuario, 403);

        $data = $request->validated();

        DB::transaction(function () use ($actor, $data, $request, $usuario): void {
            $lockedUser = Usuario::query()
                ->whereKey($usuario->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUser->load('roles.permisos:id');

            abort_unless($actor->puedeAdministrarUsuario($lockedUser)
                && $lockedUser->esAdministrador(), 403);

            $hadPin = filled($lockedUser->pin_autorizacion_hash);

            $lockedUser->update([
                'pin_autorizacion_hash' => $data['pin_autorizacion'],
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
                'campo' => 'pin_autorizacion_hash',
                'valor_anterior' => $hadPin ? 'PROTEGIDO' : 'NO CONFIGURADO',
                'valor_nuevo' => $hadPin ? 'ACTUALIZADO' : 'CONFIGURADO',
            ]);
        }, 3);

        return to_route('usuarios.edit', $usuario)
            ->with('status', "PIN administrativo de {$usuario->usuario} actualizado correctamente.");
    }
}
