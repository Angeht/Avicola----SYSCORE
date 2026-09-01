<?php

namespace App\Models;

use Database\Factories\CobranzaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['numero_cobranza', 'cliente_id', 'usuario_id', 'sesion_caja_id', 'medio_pago_id', 'tipo', 'monto_total', 'fecha_pago', 'observacion', 'anulada_por', 'anulada_at', 'motivo_anulacion'])]
class Cobranza extends Model
{
    /** @use HasFactory<CobranzaFactory> */
    use HasFactory;

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }

    public function medioPago(): BelongsTo
    {
        return $this->belongsTo(MedioPago::class, 'medio_pago_id');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulada_por');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AplicacionCobranza::class, 'cobranza_id');
    }

    public function estaAnulada(): bool
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
            $query->where('numero_cobranza', 'like', $term)
                ->orWhereHas('cliente', fn (Builder $query): Builder => $query
                    ->where('nombres_razon_social', 'like', $term)
                    ->orWhere('nro_documento', 'like', $term))
                ->orWhereHas('aplicaciones.venta', fn (Builder $query): Builder => $query
                    ->where('numero_venta', 'like', $term));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'fecha_pago' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }
}
