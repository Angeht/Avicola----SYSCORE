<?php

namespace App\Models;

use Database\Factories\AjusteProveedorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['numero_ajuste', 'carga_id', 'pago_proveedor_id', 'ajuste_mercaderia_id', 'tipo', 'monto', 'motivo', 'usuario_id', 'fecha_ajuste', 'anulado_por', 'anulado_at', 'motivo_anulacion'])]
class AjusteProveedor extends Model
{
    /** @use HasFactory<AjusteProveedorFactory> */
    use HasFactory;

    protected $table = 'ajustes_proveedor';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaProveedor::class, 'carga_id');
    }

    public function pagoProveedor(): BelongsTo
    {
        return $this->belongsTo(PagoProveedor::class, 'pago_proveedor_id');
    }

    public function ajusteMercaderia(): BelongsTo
    {
        return $this->belongsTo(AjusteMercaderia::class, 'ajuste_mercaderia_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulado_por');
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_ajuste' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
