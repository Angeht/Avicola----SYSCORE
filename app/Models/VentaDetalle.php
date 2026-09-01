<?php

namespace App\Models;

use Database\Factories\VentaDetalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['venta_id', 'precio_version_id', 'precio_aplicado_kg', 'motivo_ajuste_precio'])]
class VentaDetalle extends Model
{
    /** @use HasFactory<VentaDetalleFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'venta_detalles';

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function precioVersion(): BelongsTo
    {
        return $this->belongsTo(PrecioDiaVersion::class, 'precio_version_id');
    }

    public function pesajes(): HasMany
    {
        return $this->hasMany(PesajeVenta::class, 'venta_detalle_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_aplicado_kg' => 'decimal:4',
        ];
    }
}
