<?php

namespace App\Models;

use Database\Factories\AuditoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['usuario_id', 'tabla_afectada', 'registro_id', 'accion', 'ip', 'created_at'])]
class Auditoria extends Model
{
    /** @use HasFactory<AuditoriaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AuditoriaDetalle::class, 'auditoria_id');
    }

    public function etiquetaAccion(): string
    {
        return match ($this->accion) {
            'INSERT' => 'Creación',
            'UPDATE' => 'Modificación',
            'DELETE' => 'Eliminación',
            'ANULAR' => 'Anulación',
            'LOGIN' => 'Acceso',
            default => 'Otro cambio',
        };
    }

    public function etiquetaTabla(): string
    {
        return match ($this->tabla_afectada) {
            'ajustes_cliente' => 'Ajustes de clientes',
            'ajustes_mercaderia' => 'Ajustes de mercadería',
            'ajustes_proveedor' => 'Ajustes de proveedores',
            'aplicacion_cobranzas' => 'Aplicaciones de abonos',
            'cargas_proveedor' => 'Cargas de proveedor',
            'clientes' => 'Clientes',
            'cobranzas' => 'Cobranzas',
            'conciliaciones_mercaderia' => 'Conciliaciones de mercadería',
            'configuracion_empresa' => 'Configuración de la empresa',
            'configuracion_respaldos' => 'Configuración de respaldos',
            'pagos_proveedor' => 'Pagos a proveedores',
            'pesajes_carga' => 'Pesajes de carga',
            'pesajes_venta' => 'Pesajes de venta',
            'precio_dia_versiones', 'precios_dia' => 'Precios del día',
            'productos' => 'Productos',
            'proveedores' => 'Proveedores',
            'roles' => 'Roles',
            'sesiones_caja' => 'Sesiones de caja',
            'tipos_jaba' => 'Tipos de jaba',
            'usuarios' => 'Usuarios',
            'venta_detalles' => 'Detalles de venta',
            'ventas' => 'Ventas',
            default => str($this->tabla_afectada)->replace('_', ' ')->headline()->toString(),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
