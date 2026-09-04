<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRolRequest;
use App\Http\Requests\UpdateRolRequest;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->status($request);
        $search = $request->string('buscar')->trim()->toString();

        $roles = Rol::query()
            ->select(['id', 'nombre', 'descripcion', 'activo', 'created_at', 'updated_at'])
            ->withCount(['permisos', 'usuarios'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nombre', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%")
                        ->orWhereHas('permisos', fn (Builder $query): Builder => $query
                            ->where('codigo', 'like', "%{$search}%")
                            ->orWhere('nombre', 'like', "%{$search}%"));
                });
            })
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('activo', $status === 'activos'))
            ->orderByRaw('nombre = ? DESC', ['ADMINISTRADOR'])
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('roles.index', [
            'activeCount' => Rol::query()->where('activo', true)->count(),
            'inactiveCount' => Rol::query()->where('activo', false)->count(),
            'permissionCount' => Permiso::query()->count(),
            'roles' => $roles,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->actor($request);

        return view('roles.create', [
            'permissionsByArea' => $this->permissionsByArea($actor),
        ]);
    }

    public function store(StoreRolRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->safe()->only(['nombre', 'descripcion', 'permisos']);

        DB::transaction(function () use ($actor, $data, $request): void {
            abort_unless($actor->puedeConcederPermisos($data['permisos']), 403);

            $role = Rol::query()->create([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'],
                'activo' => true,
            ]);

            $role->permisos()->sync($data['permisos']);
            $permissionCodes = $this->permissionCodes($data['permisos']);

            $this->recordAudit($request, $actor, $role->getKey(), 'INSERT', [
                'nombre' => [null, $role->nombre],
                'descripcion' => [null, $role->descripcion],
                'activo' => [null, '1'],
                'permisos' => [null, $permissionCodes],
            ]);
        }, 3);

        return to_route('roles.index')
            ->with('status', 'Rol registrado correctamente.');
    }

    public function edit(Request $request, Rol $rol): View
    {
        $actor = $this->actor($request);

        abort_unless($actor->puedeAdministrarRol($rol), 403);

        $rol->load('permisos:id,codigo,nombre');

        return view('roles.edit', [
            'permissionsByArea' => $this->permissionsByArea($actor, $rol),
            'role' => $rol,
        ]);
    }

    public function update(UpdateRolRequest $request, Rol $rol): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->safe()->only(['nombre', 'descripcion', 'permisos']);

        DB::transaction(function () use ($actor, $data, $request, $rol): void {
            $lockedRole = Rol::query()
                ->whereKey($rol->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRole->load('permisos:id');
            abort_unless($actor->puedeAdministrarRol($lockedRole), 403);
            abort_unless($actor->puedeConcederPermisos($data['permisos']), 403);

            $isAdministratorRole = $lockedRole->nombre === 'ADMINISTRADOR';
            $permissionIds = $isAdministratorRole
                ? Permiso::query()->orderBy('id')->pluck('id')->all()
                : $data['permisos'];
            $attributes = [
                'nombre' => $isAdministratorRole ? 'ADMINISTRADOR' : $data['nombre'],
                'descripcion' => $data['descripcion'],
            ];
            $previous = [
                'nombre' => $lockedRole->nombre,
                'descripcion' => $lockedRole->descripcion,
                'permisos' => $lockedRole->permisos()->orderBy('codigo')->pluck('codigo')->join(', '),
            ];

            $lockedRole->update($attributes);
            $lockedRole->permisos()->sync($permissionIds);

            $newPermissions = $this->permissionCodes($permissionIds);
            $changes = [];

            if ($previous['nombre'] !== $attributes['nombre']) {
                $changes['nombre'] = [$previous['nombre'], $attributes['nombre']];
            }

            if ($previous['descripcion'] !== $attributes['descripcion']) {
                $changes['descripcion'] = [$previous['descripcion'], $attributes['descripcion']];
            }

            if ($previous['permisos'] !== $newPermissions) {
                $changes['permisos'] = [$previous['permisos'], $newPermissions];
            }

            if ($changes !== []) {
                $this->recordAudit($request, $actor, $lockedRole->getKey(), 'UPDATE', $changes);
            }
        }, 3);

        return to_route('roles.index')
            ->with('status', 'Rol actualizado correctamente.');
    }

    /**
     * @return Collection<string, Collection<int, Permiso>>
     */
    private function permissionsByArea(Usuario $actor, ?Rol $role = null): Collection
    {
        return Permiso::query()
            ->select(['id', 'codigo', 'nombre', 'descripcion'])
            ->when(
                $role?->nombre !== 'ADMINISTRADOR',
                fn (Builder $query): Builder => $query->whereNotIn(
                    'codigo',
                    Permiso::CODIGOS_EXCLUSIVOS_ADMINISTRADOR_SISTEMA,
                ),
            )
            ->when(
                ! $actor->esAdministrador(),
                fn (Builder $query): Builder => $query->whereIn('id', $actor->idsPermisosConcedibles()),
            )
            ->orderBy('codigo')
            ->get()
            ->groupBy(fn (Permiso $permission): string => $this->permissionArea($permission->codigo));
    }

    private function permissionArea(string $code): string
    {
        return match (true) {
            Str::startsWith($code, 'USUARIOS_') => 'Seguridad',
            Str::startsWith($code, ['CONFIGURACION_', 'TIPOS_JABA_', 'RESPALDOS_']) => 'Configuración',
            Str::startsWith($code, ['VENTAS_', 'PRECIO_']) => 'Ventas',
            Str::startsWith($code, 'COBRANZAS_') => 'Cobranzas',
            Str::startsWith($code, ['CARGAS_', 'PROVEEDORES_']) => 'Abastecimiento',
            Str::startsWith($code, 'MERCADERIA_') => 'Inventario',
            Str::startsWith($code, 'CAJA_') => 'Caja',
            Str::startsWith($code, ['REPORTES_', 'AUDITORIA_']) => 'Control',
            default => 'Otros',
        };
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function permissionCodes(array $permissionIds): string
    {
        return Permiso::query()
            ->whereKey($permissionIds)
            ->orderBy('codigo')
            ->pluck('codigo')
            ->join(', ');
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
            'tabla_afectada' => 'roles',
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
