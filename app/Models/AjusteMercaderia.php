<?php

namespace App\Models;

use Database\Factories\AjusteMercaderiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['numero_ajuste', 'producto_id', 'tipo_ajuste_id', 'cantidad_pollos', 'peso_kg', 'motivo', 'usuario_id', 'fecha_ajuste', 'anulado_por', 'anulado_at', 'motivo_anulacion'])]
class AjusteMercaderia extends Model
{
    /** @use HasFactory<AjusteMercaderiaFactory> */
    use HasFactory;

    protected $table = 'ajustes_mercaderia';

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function tipoAjuste(): BelongsTo
    {
        return $this->belongsTo(TipoAjusteMercaderia::class, 'tipo_ajuste_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulado_por');
    }

    public function conciliaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            ConciliacionMercaderia::class,
            'conciliacion_ajuste',
            'ajuste_id',
            'conciliacion_id',
        );
    }

    public function ajusteCliente(): HasOne
    {
        return $this->hasOne(AjusteCliente::class, 'ajuste_mercaderia_id');
    }

    public function ajusteProveedor(): HasOne
    {
        return $this->hasOne(AjusteProveedor::class, 'ajuste_mercaderia_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    #[Scope]
    protected function vigentes(Builder $query): void
    {
        $query->whereNull('anulado_at');
    }

    #[Scope]
    protected function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%'.Str::of($search)->trim()->replace(['%', '_'], ['\\%', '\\_']).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('numero_ajuste', 'like', $term)
                ->orWhere('motivo', 'like', $term)
                ->orWhereHas('producto', fn (Builder $query): Builder => $query
                    ->where('nombre', 'like', $term))
                ->orWhereHas('tipoAjuste', fn (Builder $query): Builder => $query
                    ->where('nombre', 'like', $term)
                    ->orWhere('codigo', 'like', $term));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_pollos' => 'integer',
            'peso_kg' => 'decimal:3',
            'fecha_ajuste' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
