<?php

namespace App\Models;

use Database\Factories\PermisoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['codigo', 'nombre', 'descripcion'])]
class Permiso extends Model
{
    /** @var list<string> */
    public const CODIGOS_EXCLUSIVOS_ADMINISTRADOR_SISTEMA = [
        'CONFIGURACION_EMPRESA_GESTIONAR',
        'RESPALDOS_GESTIONAR',
    ];

    /** @use HasFactory<PermisoFactory> */
    use HasFactory;

    public $timestamps = false;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'permiso_id', 'rol_id');
    }

    /**
     * @return list<int>
     */
    public static function idsExclusivosAdministradorSistema(): array
    {
        return self::query()
            ->whereIn('codigo', self::CODIGOS_EXCLUSIVOS_ADMINISTRADOR_SISTEMA)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $permissionIds */
    public static function incluyePermisoExclusivoAdministradorSistema(array $permissionIds): bool
    {
        return collect($permissionIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->intersect(self::idsExclusivosAdministradorSistema())
            ->isNotEmpty();
    }
}
