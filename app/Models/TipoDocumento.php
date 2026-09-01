<?php

namespace App\Models;

use Database\Factories\TipoDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'longitud_maxima', 'activo'])]
class TipoDocumento extends Model
{
    /** @use HasFactory<TipoDocumentoFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'tipos_documento';

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'tipo_documento_id');
    }

    public function proveedores(): HasMany
    {
        return $this->hasMany(Proveedor::class, 'tipo_documento_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'longitud_maxima' => 'integer',
        ];
    }
}
