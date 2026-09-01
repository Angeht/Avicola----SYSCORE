<?php

namespace App\Models;

use Database\Factories\AplicacionCobranzaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cobranza_id', 'venta_id', 'monto_aplicado'])]
class AplicacionCobranza extends Model
{
    /** @use HasFactory<AplicacionCobranzaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'aplicacion_cobranzas';

    public function cobranza(): BelongsTo
    {
        return $this->belongsTo(Cobranza::class, 'cobranza_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_aplicado' => 'decimal:2',
        ];
    }
}
