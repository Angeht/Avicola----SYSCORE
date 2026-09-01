<?php

namespace App\Models;

use Database\Factories\PesajeCargaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['carga_id', 'tipo_jaba_id', 'cantidad_jabas', 'cantidad_pollos', 'peso_bruto_kg', 'tara_unitaria_aplicada_kg', 'observacion', 'editado_por', 'autorizado_por', 'editado_at'])]
class PesajeCarga extends Model
{
    /** @use HasFactory<PesajeCargaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'pesajes_carga';

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaProveedor::class, 'carga_id');
    }

    public function tipoJaba(): BelongsTo
    {
        return $this->belongsTo(TipoJaba::class, 'tipo_jaba_id');
    }

    public function editadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'editado_por');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autorizado_por');
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
            'editado_at' => 'datetime',
        ];
    }
}
