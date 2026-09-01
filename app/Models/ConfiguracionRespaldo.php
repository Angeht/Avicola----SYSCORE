<?php

namespace App\Models;

use Database\Factories\ConfiguracionRespaldoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['activo', 'frecuencia', 'hora', 'dia_semana', 'dia_mes', 'retencion_cantidad', 'verificar_automaticamente', 'actualizado_por'])]
class ConfiguracionRespaldo extends Model
{
    /** @use HasFactory<ConfiguracionRespaldoFactory> */
    use HasFactory;

    public const FRECUENCIA_DIARIA = 'DIARIA';

    public const FRECUENCIA_SEMANAL = 'SEMANAL';

    public const FRECUENCIA_MENSUAL = 'MENSUAL';

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }

    public static function singleton(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'activo' => true,
            'frecuencia' => self::FRECUENCIA_DIARIA,
            'hora' => '02:00:00',
            'dia_semana' => 1,
            'dia_mes' => 1,
            'retencion_cantidad' => 14,
            'verificar_automaticamente' => true,
        ]);
    }

    public function etiquetaFrecuencia(): string
    {
        return match ($this->frecuencia) {
            self::FRECUENCIA_SEMANAL => 'Semanal',
            self::FRECUENCIA_MENSUAL => 'Mensual',
            default => 'Diaria',
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'dia_semana' => 'integer',
            'dia_mes' => 'integer',
            'retencion_cantidad' => 'integer',
            'verificar_automaticamente' => 'boolean',
        ];
    }
}
