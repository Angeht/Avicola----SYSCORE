<?php

namespace App\Models;

use Database\Factories\ProcesoBeneficiadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['numero_proceso', 'carga_proveedor_id', 'producto_destino_id', 'cantidad_pollos', 'peso_origen_kg', 'peso_resultante_kg', 'procesado_at', 'procesado_por', 'observacion', 'anulado_por', 'anulado_at', 'motivo_anulacion'])]
class ProcesoBeneficiado extends Model
{
    /** @use HasFactory<ProcesoBeneficiadoFactory> */
    use HasFactory;

    protected $table = 'procesos_beneficiado';

    public function cargaProveedor(): BelongsTo
    {
        return $this->belongsTo(CargaProveedor::class, 'carga_proveedor_id');
    }

    public function productoDestino(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_destino_id');
    }

    public function procesadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'procesado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulado_por');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    public function mermaKg(): float
    {
        return round((float) $this->peso_origen_kg - (float) $this->peso_resultante_kg, 3);
    }

    public function rendimientoPorcentaje(): float
    {
        $sourceWeight = (float) $this->peso_origen_kg;

        return $sourceWeight > 0
            ? round(((float) $this->peso_resultante_kg / $sourceWeight) * 100, 2)
            : 0;
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
            $query->where('numero_proceso', 'like', $term)
                ->orWhere('observacion', 'like', $term)
                ->orWhereHas('cargaProveedor', fn (Builder $query): Builder => $query
                    ->where('numero_carga', 'like', $term)
                    ->orWhereHas('proveedor', fn (Builder $query): Builder => $query
                        ->where('nombre_razon_social', 'like', $term)))
                ->orWhereHas('productoDestino', fn (Builder $query): Builder => $query
                    ->where('nombre', 'like', $term));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_pollos' => 'integer',
            'peso_origen_kg' => 'decimal:3',
            'peso_resultante_kg' => 'decimal:3',
            'procesado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
