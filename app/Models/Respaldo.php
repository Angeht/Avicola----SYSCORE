<?php

namespace App\Models;

use Database\Factories\RespaldoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nombre_archivo', 'disco', 'ruta', 'tipo', 'estado', 'tamano_bytes', 'checksum_sha256', 'creado_por', 'creado_at', 'verificado_at', 'verificado_por', 'eliminado_at', 'eliminado_por', 'error'])]
class Respaldo extends Model
{
    /** @use HasFactory<RespaldoFactory> */
    use HasFactory;

    public const TIPO_MANUAL = 'MANUAL';

    public const TIPO_AUTOMATICO = 'AUTOMATICO';

    public const TIPO_PRE_RESTAURACION = 'PRE_RESTAURACION';

    public const ESTADO_EN_PROCESO = 'EN_PROCESO';

    public const ESTADO_COMPLETADO = 'COMPLETADO';

    public const ESTADO_FALLIDO = 'FALLIDO';

    public const ESTADO_ELIMINADO = 'ELIMINADO';

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'verificado_por');
    }

    public function eliminadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'eliminado_por');
    }

    public function estaDisponible(): bool
    {
        return $this->estado === self::ESTADO_COMPLETADO && $this->eliminado_at === null;
    }

    public function estaRestaurable(): bool
    {
        return $this->estaDisponible() && $this->verificado_at !== null;
    }

    #[Scope]
    protected function disponibles(Builder $query): void
    {
        $query->where('estado', self::ESTADO_COMPLETADO)
            ->whereNull('eliminado_at');
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            self::TIPO_AUTOMATICO => 'Automático',
            self::TIPO_PRE_RESTAURACION => 'Preventivo',
            default => 'Manual',
        };
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            self::ESTADO_COMPLETADO => 'Completado',
            self::ESTADO_FALLIDO => 'Fallido',
            self::ESTADO_ELIMINADO => 'Eliminado',
            default => 'En proceso',
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'creado_at' => 'datetime',
            'verificado_at' => 'datetime',
            'eliminado_at' => 'datetime',
        ];
    }
}
