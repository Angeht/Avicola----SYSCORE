<?php

namespace Database\Factories;

use App\Models\PrecioDiaVersion;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VentaDetalle>
 */
class VentaDetalleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venta_id' => Venta::factory(),
            'precio_version_id' => PrecioDiaVersion::factory(['precio_kg' => 10]),
            'precio_aplicado_kg' => 10,
            'motivo_ajuste_precio' => null,
        ];
    }
}
