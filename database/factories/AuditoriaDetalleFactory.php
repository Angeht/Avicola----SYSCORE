<?php

namespace Database\Factories;

use App\Models\Auditoria;
use App\Models\AuditoriaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditoriaDetalle>
 */
class AuditoriaDetalleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auditoria_id' => Auditoria::factory(),
            'campo' => fake()->randomElement(['estado', 'monto_total', 'observacion']),
            'valor_anterior' => fake()->word(),
            'valor_nuevo' => fake()->word(),
        ];
    }
}
