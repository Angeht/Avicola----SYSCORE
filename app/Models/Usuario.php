<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['nombres', 'apellidos', 'usuario', 'password_hash', 'pin_autorizacion_hash', 'activo', 'ultimo_acceso'])]
#[Hidden(['password_hash', 'pin_autorizacion_hash'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, Notifiable;

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = '';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'usuario_id', 'rol_id');
    }

    public function sesionesCaja(): HasMany
    {
        return $this->hasMany(SesionCaja::class, 'usuario_id');
    }

    public function pagosProveedorRegistrados(): HasMany
    {
        return $this->hasMany(PagoProveedor::class, 'pagado_por');
    }

    public function pagosProveedorAnulados(): HasMany
    {
        return $this->hasMany(PagoProveedor::class, 'anulada_por');
    }

    public function ventasRegistradas(): HasMany
    {
        return $this->hasMany(Venta::class, 'usuario_id');
    }

    public function ventasAnuladas(): HasMany
    {
        return $this->hasMany(Venta::class, 'anulada_por');
    }

    public function ventasEditadas(): HasMany
    {
        return $this->hasMany(Venta::class, 'editada_por');
    }

    public function cobranzasRegistradas(): HasMany
    {
        return $this->hasMany(Cobranza::class, 'usuario_id');
    }

    public function cobranzasAnuladas(): HasMany
    {
        return $this->hasMany(Cobranza::class, 'anulada_por');
    }

    public function ajustesMercaderiaRegistrados(): HasMany
    {
        return $this->hasMany(AjusteMercaderia::class, 'usuario_id');
    }

    public function ajustesMercaderiaAnulados(): HasMany
    {
        return $this->hasMany(AjusteMercaderia::class, 'anulado_por');
    }

    public function conciliacionesMercaderia(): HasMany
    {
        return $this->hasMany(ConciliacionMercaderia::class, 'usuario_id');
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function iniciales(): string
    {
        return Str::of($this->nombreCompleto())
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $parte): string => Str::upper(Str::substr($parte, 0, 1)))
            ->implode('');
    }

    public function esAdministrador(): bool
    {
        $this->loadMissing('roles');

        return $this->activo && $this->roles->contains(
            fn (Rol $rol): bool => $rol->activo && $rol->nombre === 'ADMINISTRADOR',
        );
    }

    public function puedeEliminarVentas(): bool
    {
        $this->loadMissing('roles');

        return $this->activo && $this->roles->contains(
            fn (Rol $rol): bool => $rol->activo
                && in_array($rol->nombre, ['ADMINISTRADOR', 'ADMINISTRADOR OPERATIVO'], true),
        );
    }

    public function tienePermiso(string $codigo): bool
    {
        return $this->tieneAlgunPermiso([$codigo]);
    }

    /**
     * @param  list<string>  $codigos
     */
    public function tieneAlgunPermiso(array $codigos): bool
    {
        $this->loadMissing('roles.permisos');

        if (! $this->activo) {
            return false;
        }

        if ($this->esAdministrador()) {
            return true;
        }

        return $this->roles->contains(
            fn (Rol $rol): bool => $rol->activo && $rol->permisos->contains(
                fn (Permiso $permiso): bool => in_array($permiso->codigo, $codigos, true),
            ),
        );
    }

    /**
     * @return list<int>
     */
    public function idsPermisosConcedibles(): array
    {
        if ($this->esAdministrador()) {
            return Permiso::query()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        $this->loadMissing('roles.permisos:id');

        return $this->roles
            ->where('activo', true)
            ->flatMap(fn (Rol $role): Collection => $role->permisos->pluck('id'))
            ->unique()
            ->sort()
            ->values()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $permissionIds */
    public function puedeConcederPermisos(array $permissionIds): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        return collect($permissionIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->diff($this->idsPermisosConcedibles())
            ->isEmpty();
    }

    /** @param list<int> $roleIds */
    public function puedeAsignarRoles(array $roleIds): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        $requestedRoleIds = collect($roleIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $roles = Rol::query()
            ->whereKey($requestedRoleIds->all())
            ->where('activo', true)
            ->with('permisos:id')
            ->get();

        return $roles->count() === $requestedRoleIds->count()
            && $roles->every(fn (Rol $role): bool => $role->nombre !== 'ADMINISTRADOR'
                && $this->puedeConcederPermisos($role->permisos->modelKeys()));
    }

    public function puedeAdministrarRol(Rol $role): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        $role->loadMissing('permisos:id');

        return $role->nombre !== 'ADMINISTRADOR'
            && $this->puedeConcederPermisos($role->permisos->modelKeys());
    }

    public function puedeAdministrarUsuario(Usuario $user): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        $user->loadMissing('roles.permisos:id');

        return $user->roles->doesntContain(
            fn (Rol $role): bool => $role->nombre === 'ADMINISTRADOR'
                || ! $this->puedeConcederPermisos($role->permisos->modelKeys()),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso' => 'datetime',
            'password_hash' => 'hashed',
            'pin_autorizacion_hash' => 'hashed',
        ];
    }
}
