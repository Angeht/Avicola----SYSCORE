<?php

namespace App\Models;

use Database\Factories\SesionCajaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['usuario_id', 'fecha_operacion', 'apertura_at', 'apertura_autorizada_por', 'cierre_at', 'cerrada_por', 'cierre_autorizada_por', 'monto_apertura', 'monto_contado_efectivo', 'observacion_cierre'])]
class SesionCaja extends Model
{
    /** @use HasFactory<SesionCajaFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'sesiones_caja';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrada_por');
    }

    public function aperturaAutorizadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'apertura_autorizada_por');
    }

    public function cierreAutorizadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cierre_autorizada_por');
    }

    public function pagosProveedor(): HasMany
    {
        return $this->hasMany(PagoProveedor::class, 'sesion_caja_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'sesion_caja_id');
    }

    public function cobranzas(): HasMany
    {
        return $this->hasMany(Cobranza::class, 'sesion_caja_id');
    }

    public function estaAbierta(): bool
    {
        return $this->cierre_at === null;
    }

    #[Scope]
    protected function abiertas(Builder $query): void
    {
        $query->whereNull('cierre_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_operacion' => 'date',
            'apertura_at' => 'datetime',
            'cierre_at' => 'datetime',
            'monto_apertura' => 'decimal:2',
            'monto_contado_efectivo' => 'decimal:2',
        ];
    }
}
