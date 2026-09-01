<?php

namespace App\Models;

use Database\Factories\UnidadMedidaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'simbolo', 'activo'])]
class UnidadMedida extends Model
{
    /** @use HasFactory<UnidadMedidaFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'unidades_medida';

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'unidad_medida_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
