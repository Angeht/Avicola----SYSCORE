<?php

namespace App\Models;

use Database\Factories\PrecioDiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['producto_id', 'fecha'])]
class PrecioDia extends Model
{
    /** @use HasFactory<PrecioDiaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'precios_dia';

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(PrecioDiaVersion::class, 'precio_dia_id');
    }

    public function versionVigente(): HasOne
    {
        return $this->hasOne(PrecioDiaVersion::class, 'precio_dia_id')->ofMany([
            'vigente_desde' => 'max',
            'id' => 'max',
        ], fn (Builder $query): Builder => $query->where('precio_dia_versiones.vigente_desde', '<=', now()));
    }

    #[Scope]
    protected function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%'.Str::of($search)->trim()->replace(['%', '_'], ['\\%', '\\_']).'%';

        $query->whereHas('producto', fn (Builder $query): Builder => $query->where('nombre', 'like', $term));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }
}
