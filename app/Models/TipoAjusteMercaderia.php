<?php

namespace App\Models;

use Database\Factories\TipoAjusteMercaderiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'naturaleza', 'requiere_motivo', 'activo'])]
class TipoAjusteMercaderia extends Model
{
    /** @use HasFactory<TipoAjusteMercaderiaFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'tipos_ajuste_mercaderia';

    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteMercaderia::class, 'tipo_ajuste_id');
    }

    public function esEntrada(): bool
    {
        return $this->naturaleza === 'ENTRADA';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requiere_motivo' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
