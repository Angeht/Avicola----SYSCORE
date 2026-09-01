<?php

namespace App\Models;

use Database\Factories\TipoJabaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['nombre', 'tara_referencial_kg', 'descripcion', 'activo'])]
class TipoJaba extends Model
{
    /** @use HasFactory<TipoJabaFactory> */
    use HasFactory;

    protected $table = 'tipos_jaba';

    public function pesajesCarga(): HasMany
    {
        return $this->hasMany(PesajeCarga::class, 'tipo_jaba_id');
    }

    public function pesajesVenta(): HasMany
    {
        return $this->hasMany(PesajeVenta::class, 'tipo_jaba_id');
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
                ->orWhere('descripcion', 'like', $term);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tara_referencial_kg' => 'decimal:3',
            'activo' => 'boolean',
        ];
    }
}
