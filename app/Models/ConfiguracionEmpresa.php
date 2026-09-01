<?php

namespace App\Models;

use Database\Factories\ConfiguracionEmpresaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'razon_social',
    'nombre_comercial',
    'tipo_documento_id',
    'nro_documento',
    'direccion',
    'telefono',
    'mensaje_ticket',
])]
class ConfiguracionEmpresa extends Model
{
    /** @use HasFactory<ConfiguracionEmpresaFactory> */
    use HasFactory;

    public const CREATED_AT = null;

    protected $table = 'configuracion_empresa';

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }
}
