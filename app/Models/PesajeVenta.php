<?php

namespace App\Models;

use Database\Factories\PesajeVentaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venta_detalle_id', 'tipo_pesaje', 'tipo_jaba_id', 'cantidad_jabas', 'cantidad_pollos', 'peso_bruto_kg', 'tara_unitaria_aplicada_kg', 'observacion'])]
class PesajeVenta extends Model
{
    /** @use HasFactory<PesajeVentaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'pesajes_venta';

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
    }

    public function tipoJaba(): BelongsTo
    {
        return $this->belongsTo(TipoJaba::class, 'tipo_jaba_id');
    }

    protected function taraTotalKg(): Attribute
    {
        return Attribute::get(fn (): float => round(
            $this->cantidad_jabas * (float) $this->tara_unitaria_aplicada_kg,
            3,
        ));
    }

    protected function pesoNetoKg(): Attribute
    {
        return Attribute::get(fn (): float => round(
            (float) $this->peso_bruto_kg - $this->tara_total_kg,
            3,
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_jabas' => 'integer',
            'cantidad_pollos' => 'integer',
            'peso_bruto_kg' => 'decimal:3',
            'tara_unitaria_aplicada_kg' => 'decimal:3',
        ];
    }
}
