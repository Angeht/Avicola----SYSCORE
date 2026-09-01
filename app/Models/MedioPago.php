<?php

namespace App\Models;

use Database\Factories\MedioPagoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'es_efectivo', 'activo'])]
class MedioPago extends Model
{
    /** @use HasFactory<MedioPagoFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'medios_pago';

    public function pagosProveedor(): HasMany
    {
        return $this->hasMany(PagoProveedor::class, 'medio_pago_id');
    }

    public function cobranzas(): HasMany
    {
        return $this->hasMany(Cobranza::class, 'medio_pago_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_efectivo' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
