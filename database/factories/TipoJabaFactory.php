<?php

namespace Database\Factories;

use App\Models\TipoJaba;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoJaba>
 */
class TipoJabaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_strtoupper(fake()->unique()->bothify('JABA TIPO ??-##')),
            'tara_referencial_kg' => fake()->randomFloat(3, 1, 5),
            'descripcion' => fake()->optional()->sentence(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }
}
