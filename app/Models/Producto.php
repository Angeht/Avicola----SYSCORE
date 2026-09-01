<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['nombre', 'descripcion', 'unidad_medida_id', 'modalidad_venta', 'activo'])]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    public const MODALIDAD_PESAJE_VIVO = 'PESAJE_VIVO';

    public const MODALIDAD_SOLO_PESO = 'SOLO_PESO';

    /**
     * @return array<string, string>
     */
    public static function modalidadesVenta(): array
    {
        return [
            self::MODALIDAD_PESAJE_VIVO => 'Pesaje vivo (aves, jabas y tara)',
            self::MODALIDAD_SOLO_PESO => 'Solo peso (kilogramos)',
        ];
    }

    public function seVendeSoloPorPeso(): bool
    {
        return $this->modalidad_venta === self::MODALIDAD_SOLO_PESO;
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    public function preciosDia(): HasMany
    {
        return $this->hasMany(PrecioDia::class, 'producto_id');
    }

    public function ajustesMercaderia(): HasMany
    {
        return $this->hasMany(AjusteMercaderia::class, 'producto_id');
    }

    public function conciliacionesMercaderia(): HasMany
    {
        return $this->hasMany(ConciliacionMercaderia::class, 'producto_id');
    }

    public function procesosBeneficiadoDestino(): HasMany
    {
        return $this->hasMany(ProcesoBeneficiado::class, 'producto_destino_id');
    }

    #[Scope]
    protected function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%'.Str::of($search)->trim()->replace(['%', '_'], ['\\%', '\\_']).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('nombre', 'like', $term)
                ->orWhere('descripcion', 'like', $term)
                ->orWhereHas('unidadMedida', fn (Builder $query) => $query
                    ->where('codigo', 'like', $term)
                    ->orWhere('nombre', 'like', $term));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
