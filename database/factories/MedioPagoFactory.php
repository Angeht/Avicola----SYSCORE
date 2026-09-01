<?php

namespace Database\Factories;

use App\Models\MedioPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedioPago>
 */
class MedioPagoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => mb_strtoupper(fake()->unique()->bothify('MEDIO_##??')),
            'nombre' => mb_strtoupper(fake()->unique()->words(2, true)),
            'es_efectivo' => false,
            'activo' => true,
        ];
    }

    public function efectivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'es_efectivo' => true,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }
}
