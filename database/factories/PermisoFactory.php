<?php

namespace Database\Factories;

use App\Models\Permiso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permiso>
 */
class PermisoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('PERMISO_####'),
            'nombre' => fake()->sentence(3),
            'descripcion' => fake()->optional()->sentence(),
        ];
    }
}
