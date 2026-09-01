<?php

namespace App\Models;

use Database\Factories\AuditoriaDetalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['auditoria_id', 'campo', 'valor_anterior', 'valor_nuevo'])]
class AuditoriaDetalle extends Model
{
    /** @use HasFactory<AuditoriaDetalleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'auditoria_detalles';

    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class, 'auditoria_id');
    }

    public function etiquetaCampo(): string
    {
        return match ($this->campo) {
            'anulada_at' => 'Fecha de anulación',
            'anulada_por', 'anulado_por' => 'Anulado por',
            'cerrada_por' => 'Cerrada por',
            'cierre_at' => 'Fecha de cierre',
            'created_at' => 'Fecha de creación',
            'direccion' => 'Dirección',
            'fecha_ajuste' => 'Fecha del ajuste',
            'fecha_carga' => 'Fecha de carga',
            'fecha_pago' => 'Fecha de pago',
            'fecha_venta' => 'Fecha de venta',
            'motivo_anulacion' => 'Motivo de anulación',
            'monto_total' => 'Monto total',
            'mensaje_ticket' => 'Mensaje del comprobante',
            'nombre_comercial' => 'Nombre comercial',
            'numero_ajuste' => 'Número de ajuste',
            'numero_carga' => 'Número de carga',
            'numero_cobranza' => 'Número de cobranza',
            'numero_pago' => 'Número de pago',
            'numero_venta' => 'Número de venta',
            'nro_documento' => 'Número de documento',
            'password_hash' => 'Contraseña',
            'razon_social' => 'Razón social',
            'tara_referencial_kg' => 'Tara referencial (kg)',
            'telefono' => 'Teléfono',
            'tipo_documento_id' => 'Tipo de documento',
            'updated_at' => 'Última modificación',
            default => str($this->campo)->replace('_', ' ')->headline()->toString(),
        };
    }
}
