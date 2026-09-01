<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolActivationController extends Controller
{
    public function store(Request $request, Rol $rol): RedirectResponse
    {
        $actor = $this->actor($request);

        DB::transaction(function () use ($actor, $request, $rol): void {
            $lockedRole = Rol::query()
                ->whereKey($rol->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRole->load('permisos:id');

            abort_unless($actor->puedeAdministrarRol($lockedRole), 403);

            if ($lockedRole->activo) {
                return;
            }

            $lockedRole->update(['activo' => true]);
            $this->recordAudit($request, $actor, $lockedRole->getKey(), '0', '1');
        }, 3);

        return to_route('roles.index')
            ->with('status', 'Rol activado correctamente.');
    }

    public function destroy(Request $request, Rol $rol): RedirectResponse
    {
        $actor = $this->actor($request);

        DB::transaction(function () use ($actor, $request, $rol): void {
            $lockedRole = Rol::query()
                ->whereKey($rol->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRole->load('permisos:id');

            abort_unless($actor->puedeAdministrarRol($lockedRole), 403);

            if ($lockedRole->nombre === 'ADMINISTRADOR') {
                throw ValidationException::withMessages([
                    'rol' => 'El rol ADMINISTRADOR es estructural y no puede desactivarse.',
                ]);
            }

            if (! $actor->esAdministrador() && $actor->roles()->whereKey($lockedRole)->exists()) {
                throw ValidationException::withMessages([
                    'rol' => 'No puedes desactivar el rol que autoriza tu sesión actual.',
                ]);
            }

            if (! $lockedRole->activo) {
                return;
            }

            $lockedRole->update(['activo' => false]);
            $this->recordAudit($request, $actor, $lockedRole->getKey(), '1', '0');
        }, 3);

        return to_route('roles.index')
            ->with('status', 'Rol desactivado. Sus asignaciones se conservaron sin conceder permisos.');
    }

    private function actor(Request $request): Usuario
    {
        $actor = $request->user();

        abort_unless($actor instanceof Usuario && $actor->tienePermiso('USUARIOS_GESTIONAR'), 403);

        return $actor;
    }

    private function recordAudit(
        Request $request,
        Usuario $actor,
        int $recordId,
        string $previous,
        string $new,
    ): void {
        $auditId = DB::table('auditorias')->insertGetId([
            'usuario_id' => $actor->getKey(),
            'tabla_afectada' => 'roles',
            'registro_id' => $recordId,
            'accion' => 'UPDATE',
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        DB::table('auditoria_detalles')->insert([
            'auditoria_id' => $auditId,
            'campo' => 'activo',
            'valor_anterior' => $previous,
            'valor_nuevo' => $new,
        ]);
    }
}
