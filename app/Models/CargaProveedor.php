<?php

namespace App\Models;

use Database\Factories\CargaProveedorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['numero_carga', 'proveedor_id', 'producto_id', 'fecha_carga', 'costo_kg', 'costo_total', 'recibido_por', 'observacion', 'anulada_por', 'anulada_at', 'motivo_anulacion'])]
class CargaProveedor extends Model
{
    /** @use HasFactory<CargaProveedorFactory> */
    use HasFactory;

    protected $table = 'cargas_proveedor';

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulada_por');
    }

    public function pesajes(): HasMany
    {
        return $this->hasMany(PesajeCarga::class, 'carga_id');
    }

    public function pagosProveedor(): HasMany
    {
        return $this->hasMany(PagoProveedor::class, 'carga_id');
    }

    public function procesosBeneficiado(): HasMany
    {
        return $this->hasMany(ProcesoBeneficiado::class, 'carga_proveedor_id');
    }

    public function estaAnulada(): bool
    {
        return $this->anulada_at !== null;
    }

    public function tienePagosVigentes(): bool
    {
        return $this->pagosProveedor()
            ->whereNull('anulada_at')
            ->exists();
    }

    #[Scope]
    protected function vigentes(Builder $query): void
    {
        $query->whereNull('anulada_at');
    }

    #[Scope]
    protected function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%'.Str::of($search)->trim()->replace(['%', '_'], ['\\%', '\\_']).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('numero_carga', 'like', $term)
                ->orWhereHas('proveedor', fn (Builder $query): Builder => $query
                    ->where('nombre_razon_social', 'like', $term))
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
            'fecha_carga' => 'date',
            'costo_kg' => 'decimal:4',
            'costo_total' => 'decimal:2',
            'anulada_at' => 'datetime',
        ];
    }
}
