<?php

namespace App\Models;

use Database\Factories\ConciliacionMercaderiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['numero_conciliacion', 'producto_id', 'fecha_operacion', 'tipo_conciliacion', 'usuario_id', 'cantidad_pollos_sistema', 'peso_sistema_kg', 'cantidad_pollos_fisico', 'peso_fisico_kg', 'observacion', 'realizada_at'])]
class ConciliacionMercaderia extends Model
{
    /** @use HasFactory<ConciliacionMercaderiaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'conciliaciones_mercaderia';

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ajustes(): BelongsToMany
    {
        return $this->belongsToMany(
            AjusteMercaderia::class,
            'conciliacion_ajuste',
            'conciliacion_id',
            'ajuste_id',
        );
    }

    public function diferenciaPollos(): int
    {
        return $this->cantidad_pollos_fisico - $this->cantidad_pollos_sistema;
    }

    public function diferenciaPesoGramos(): int
    {
        return (int) round(((float) $this->peso_fisico_kg - (float) $this->peso_sistema_kg) * 1000);
    }

    public function estaCuadrada(): bool
    {
        return $this->diferenciaPollos() === 0 && $this->diferenciaPesoGramos() === 0;
    }

    #[Scope]
    protected function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%'.Str::of($search)->trim()->replace(['%', '_'], ['\\%', '\\_']).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('numero_conciliacion', 'like', $term)
                ->orWhere('observacion', 'like', $term)
                ->orWhereHas('producto', fn (Builder $query): Builder => $query
                    ->where('nombre', 'like', $term));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_operacion' => 'date',
            'cantidad_pollos_sistema' => 'integer',
            'peso_sistema_kg' => 'decimal:3',
            'cantidad_pollos_fisico' => 'integer',
            'peso_fisico_kg' => 'decimal:3',
            'realizada_at' => 'datetime',
        ];
    }
}
