<?php

namespace App\Models;

use Database\Factories\PagoProveedorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['numero_pago', 'carga_id', 'sesion_caja_id', 'medio_pago_id', 'monto', 'pagado_por', 'pagado_at', 'observacion', 'anulada_por', 'anulada_at', 'motivo_anulacion'])]
class PagoProveedor extends Model
{
    /** @use HasFactory<PagoProveedorFactory> */
    use HasFactory;

    protected $table = 'pagos_proveedor';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaProveedor::class, 'carga_id');
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class, 'medio_pago_id');
    }

    public function pagadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'pagado_por');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulada_por');
    }

    public function ajustesProveedor(): HasMany
    {
        return $this->hasMany(AjusteProveedor::class, 'pago_proveedor_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulada_at !== null;
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
            $query->where('numero_pago', 'like', $term)
                ->orWhereHas('carga', fn (Builder $query): Builder => $query
                    ->where('numero_carga', 'like', $term)
                    ->orWhereHas('proveedor', fn (Builder $query): Builder => $query
                        ->where('nombre_razon_social', 'like', $term)));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'pagado_at' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }
}
