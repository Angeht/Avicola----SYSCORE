<?php

namespace App\Models;

use Database\Factories\RestauracionRespaldoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operacion_uuid', 'respaldo_nombre', 'respaldo_ruta', 'respaldo_preventivo_nombre', 'respaldo_preventivo_ruta', 'estado', 'solicitado_por', 'solicitante', 'iniciado_at', 'completado_at', 'error'])]
class RestauracionRespaldo extends Model
{
    /** @use HasFactory<RestauracionRespaldoFactory> */
    use HasFactory;

    public const ESTADO_EN_PROCESO = 'EN_PROCESO';

    public const ESTADO_COMPLETADA = 'COMPLETADA';

    public const ESTADO_FALLIDA_REVERTIDA = 'FALLIDA_REVERTIDA';

    public const ESTADO_FALLIDA_CRITICA = 'FALLIDA_CRITICA';

    protected $table = 'restauraciones_respaldo';

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitado_por');
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            self::ESTADO_COMPLETADA => 'Completada',
            self::ESTADO_FALLIDA_REVERTIDA => 'Fallida y revertida',
            self::ESTADO_FALLIDA_CRITICA => 'Fallo crítico',
            default => 'En proceso',
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
            'completado_at' => 'datetime',
        ];
    }
}
