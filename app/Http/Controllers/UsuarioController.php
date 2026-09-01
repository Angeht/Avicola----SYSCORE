<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->status($request);
        $search = $request->string('buscar')->trim()->toString();

        $users = Usuario::query()
            ->select(['id', 'nombres', 'apellidos', 'usuario', 'activo', 'ultimo_acceso', 'created_at', 'updated_at'])
            ->with('roles:id,nombre,activo')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nombres', 'like', "%{$search}%")
                        ->orWhere('apellidos', 'like', "%{$search}%")
                        ->orWhere('usuario', 'like', "%{$search}%")
                        ->orWhereHas('roles', fn (Builder $query): Builder => $query->where('nombre', 'like', "%{$search}%"));
                });
            })
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('activo', $status === 'activos'))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('usuarios.index', [
            'activeCount' => Usuario::query()->where('activo', true)->count(),
            'administratorCount' => Usuario::query()
                ->where('activo', true)
                ->whereHas('roles', fn (Builder $query): Builder => $query
                    ->where('nombre', 'ADMINISTRADOR')
                    ->where('activo', true))
                ->count(),
            'currentUserId' => $request->user()?->getAuthIdentifier(),
            'inactiveCount' => Usuario::query()->where('activo', false)->count(),
            'search' => $search,
            'status' => $status,
            'users' => $users,
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->actor($request);

        return view('usuarios.create', [
            'roles' => $this->activeRoles($actor),
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->safe()->only(['nombres', 'apellidos', 'usuario', 'password', 'roles']);

        DB::transaction(function () use ($actor, $data, $request): void {
            abort_unless($actor->puedeAsignarRoles($data['roles']), 403);

            $roleNames = Rol::query()
                ->whereKey($data['roles'])
                ->orderBy('nombre')
                ->pluck('nombre')
                ->join(', ');
            $user = Usuario::query()->create([
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'usuario' => $data['usuario'],
                'password_hash' => $data['password'],
                'activo' => true,
            ]);

            $user->roles()->sync($data['roles']);

            $this->recordAudit($request, $actor, $user->getKey(), 'INSERT', [
                'nombres' => [null, $user->nombres],
                'apellidos' => [null, $user->apellidos],
                'usuario' => [null, $user->usuario],
                'activo' => [null, '1'],
                'roles' => [null, $roleNames],
            ]);
        }, 3);

        return to_route('usuarios.index')
            ->with('status', 'Usuario registrado correctamente.');
    }

    public function edit(Request $request, Usuario $usuario): View
    {
        $actor = $this->actor($request);

        abort_unless($actor->puedeAdministrarUsuario($usuario), 403);

        $usuario->load('roles:id,nombre,activo');

        return view('usuarios.edit', [
            'roles' => $this->activeRoles($actor),
            'user' => $usuario,
        ]);
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->safe()->only(['nombres', 'apellidos', 'usuario', 'roles']);

        DB::transaction(function () use ($actor, $data, $request, $usuario): void {
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
            abort_unless($actor->puedeAsignarRoles($data['roles']), 403);

            $this->guardAdministratorRoleRemoval($actor, $lockedUser, $administratorRole, $data['roles']);

            $previous = [
                'nombres' => $lockedUser->nombres,
                'apellidos' => $lockedUser->apellidos,
                'usuario' => $lockedUser->usuario,
            ];
            $previousRoles = $lockedUser->roles->pluck('nombre')->sort()->values()->join(', ');

            $lockedUser->update([
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'usuario' => $data['usuario'],
            ]);
            $lockedUser->roles()->sync($data['roles']);

            $newRoles = Rol::query()
                ->whereKey($data['roles'])
                ->orderBy('nombre')
                ->pluck('nombre')
                ->join(', ');
            $changes = [];

            foreach (['nombres', 'apellidos', 'usuario'] as $field) {
                if ($previous[$field] !== $data[$field]) {
                    $changes[$field] = [$previous[$field], $data[$field]];
                }
            }

            if ($previousRoles !== $newRoles) {
                $changes['roles'] = [$previousRoles, $newRoles];
            }

            if ($changes !== []) {
                $this->recordAudit($request, $actor, $lockedUser->getKey(), 'UPDATE', $changes);
            }
        }, 3);

        return to_route('usuarios.index')
            ->with('status', 'Usuario actualizado correctamente.');
    }

    /**
     * @return Collection<int, Rol>
     */
    private function activeRoles(Usuario $actor): Collection
    {
        return Rol::query()
            ->select(['id', 'nombre', 'descripcion'])
            ->where('activo', true)
            ->with('permisos:id')
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Rol $role): bool => $actor->esAdministrador()
                || ($role->nombre !== 'ADMINISTRADOR'
                    && $actor->puedeConcederPermisos($role->permisos->modelKeys())))
            ->values();
    }

    /**
     * @param  list<int>  $newRoleIds
     */
    private function guardAdministratorRoleRemoval(
        Usuario $actor,
        Usuario $target,
        ?Rol $administratorRole,
        array $newRoleIds,
    ): void {
        if ($administratorRole === null
            || ! $target->activo
            || ! $target->roles->contains('id', $administratorRole->getKey())
            || in_array($administratorRole->getKey(), $newRoleIds, true)) {
            return;
        }

        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes retirar tu propio rol de administrador.',
            ]);
        }

        $activeAdministratorCount = Usuario::query()
            ->where('activo', true)
            ->whereHas('roles', fn (Builder $query): Builder => $query
                ->whereKey($administratorRole)
                ->where('activo', true))
            ->count();

        if ($activeAdministratorCount <= 1) {
            throw ValidationException::withMessages([
                'roles' => 'Debe permanecer al menos un administrador activo.',
            ]);
        }
    }

    private function actor(Request $request): Usuario
    {
        $actor = $request->user();

        abort_unless($actor instanceof Usuario, 403);

        return $actor;
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    private function recordAudit(
        Request $request,
        Usuario $actor,
        int $recordId,
        string $action,
        array $changes,
    ): void {
        $auditId = DB::table('auditorias')->insertGetId([
            'usuario_id' => $actor->getKey(),
            'tabla_afectada' => 'usuarios',
            'registro_id' => $recordId,
            'accion' => $action,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        DB::table('auditoria_detalles')->insert(collect($changes)
            ->map(fn (array $values, string $field): array => [
                'auditoria_id' => $auditId,
                'campo' => $field,
                'valor_anterior' => $values[0],
                'valor_nuevo' => $values[1],
            ])
            ->values()
            ->all());
    }

    private function status(Request $request): string
    {
        $status = $request->string('estado')->toString();

        return in_array($status, ['todos', 'activos', 'inactivos'], true)
            ? $status
            : 'activos';
    }
}
