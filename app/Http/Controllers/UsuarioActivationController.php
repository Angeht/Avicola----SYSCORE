<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsuarioActivationController extends Controller
{
    public function store(Request $request, Usuario $usuario): RedirectResponse
    {
        $actor = $this->actor($request);

        DB::transaction(function () use ($actor, $request, $usuario): void {
            $lockedUser = Usuario::query()
                ->whereKey($usuario->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUser->load('roles.permisos:id');

            abort_unless($actor->puedeAdministrarUsuario($lockedUser), 403);

            if ($lockedUser->activo) {
                return;
            }

            $lockedUser->update(['activo' => true]);
            $this->recordAudit($request, $actor, $lockedUser->getKey(), '0', '1');
        }, 3);

        return to_route('usuarios.index')
            ->with('status', 'Usuario activado correctamente.');
    }

    public function destroy(Request $request, Usuario $usuario): RedirectResponse
    {
        $actor = $this->actor($request);

        DB::transaction(function () use ($actor, $request, $usuario): void {
            $administratorRole = Rol::query()
                ->where('nombre', 'ADMINISTRADOR')
                ->lockForUpdate()
                ->first();
            $lockedUser = Usuario::query()
                ->whereKey($usuario->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUser->load('roles.permisos:id');

            abort_unless($actor->puedeAdministrarUsuario($lockedUser), 403);

            if ($actor->is($lockedUser)) {
                throw ValidationException::withMessages([
                    'usuario' => 'No puedes desactivar tu propia cuenta.',
                ]);
            }

            if (! $lockedUser->activo) {
                return;
            }

            if ($administratorRole !== null
                && $lockedUser->roles()->whereKey($administratorRole)->where('activo', true)->exists()) {
                $activeAdministratorCount = Usuario::query()
                    ->where('activo', true)
                    ->whereHas('roles', fn (Builder $query): Builder => $query
                        ->whereKey($administratorRole)
                        ->where('activo', true))
                    ->count();

                if ($activeAdministratorCount <= 1) {
                    throw ValidationException::withMessages([
                        'usuario' => 'Debe permanecer al menos un administrador activo.',
                    ]);
                }
            }

            $lockedUser->update(['activo' => false]);
            $this->recordAudit($request, $actor, $lockedUser->getKey(), '1', '0');
        }, 3);

        return to_route('usuarios.index')
            ->with('status', 'Usuario desactivado. Su historial se mantiene disponible.');
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
            'tabla_afectada' => 'usuarios',
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
