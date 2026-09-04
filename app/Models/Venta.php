<?php

namespace App\Models;

use Database\Factories\VentaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['numero_venta', 'cliente_id', 'usuario_id', 'sesion_caja_id', 'fecha_venta', 'anulada_por', 'anulada_at', 'motivo_anulacion', 'observacion', 'editada_por', 'editada_at', 'motivo_edicion'])]
class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory;

    protected $table = 'ventas';

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

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulada_por');
    }

    public function editadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'editada_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function aplicacionesCobranza(): HasMany
    {
        return $this->hasMany(AplicacionCobranza::class, 'venta_id');
    }

    public function ajustesCliente(): HasMany
    {
        return $this->hasMany(AjusteCliente::class, 'venta_id');
    }

    public function estaAnulada(): bool
    {
        return $this->anulada_at !== null;
    }

    #[Scope]
    protected function search(Builder $query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $term = '%'.Str::of($search)->trim()->replace(['%', '_'], ['\\%', '\\_']).'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('numero_venta', 'like', $term)
                ->orWhereHas('cliente', fn (Builder $query): Builder => $query
                    ->where('nombres_razon_social', 'like', $term)
                    ->orWhere('nro_documento', 'like', $term));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_venta' => 'datetime',
            'anulada_at' => 'datetime',
            'editada_at' => 'datetime',
        ];
    }
}
