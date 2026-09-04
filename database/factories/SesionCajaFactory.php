<?php

namespace Database\Factories;

use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SesionCaja>
 */
class SesionCajaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'fecha_operacion' => today(),
            'apertura_at' => now(),
            'apertura_autorizada_por' => null,
            'cierre_at' => null,
            'cerrada_por' => null,
            'cierre_autorizada_por' => null,
            'monto_apertura' => fake()->randomFloat(2, 0, 500),
            'monto_contado_efectivo' => null,
            'observacion_cierre' => null,
        ];
    }

    public function cerrada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cierre_at' => now(),
            'cerrada_por' => Usuario::factory(),
            'monto_contado_efectivo' => $attributes['monto_apertura'],
            'observacion_cierre' => 'Cierre conforme.',
        ]);
    }
}
