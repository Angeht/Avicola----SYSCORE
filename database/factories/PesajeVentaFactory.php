<?php

namespace Database\Factories;

use App\Models\PesajeVenta;
use App\Models\TipoJaba;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PesajeVenta>
 */
class PesajeVentaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venta_detalle_id' => VentaDetalle::factory(),
            'tipo_pesaje' => 'CON_JABA',
            'tipo_jaba_id' => TipoJaba::factory(['tara_referencial_kg' => 2.200]),
            'cantidad_jabas' => 10,
            'cantidad_pollos' => 100,
            'peso_bruto_kg' => 250,
            'tara_unitaria_aplicada_kg' => 2.200,
            'observacion' => fake()->optional()->sentence(),
        ];
    }

    public function directo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo_pesaje' => 'DIRECTO',
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'tara_unitaria_aplicada_kg' => 0,
        ]);
    }
}
