<?php

namespace Database\Factories;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnidadMedida>
 */
class UnidadMedidaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => mb_strtoupper(fake()->unique()->bothify('UM##')),
            'nombre' => fake()->unique()->word(),
            'simbolo' => fake()->unique()->lexify('???'),
            'activo' => true,
        ];
    }
}
