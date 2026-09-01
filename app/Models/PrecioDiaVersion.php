<?php

namespace App\Models;

use Database\Factories\PrecioDiaVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['precio_dia_id', 'precio_kg', 'vigente_desde', 'registrado_por', 'motivo_cambio'])]
class PrecioDiaVersion extends Model
{
    /** @use HasFactory<PrecioDiaVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'precio_dia_versiones';

    public function precioDia(): BelongsTo
    {
        return $this->belongsTo(PrecioDia::class, 'precio_dia_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'precio_version_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_kg' => 'decimal:4',
            'vigente_desde' => 'datetime',
        ];
    }
}
