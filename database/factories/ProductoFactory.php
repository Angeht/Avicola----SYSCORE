<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_strtoupper(fake()->unique()->words(2, true)),
            'descripcion' => fake()->optional()->sentence(),
            'unidad_medida_id' => UnidadMedida::factory(),
            'modalidad_venta' => Producto::MODALIDAD_PESAJE_VIVO,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }

    public function soloPeso(): static
    {
        return $this->state(fn (array $attributes): array => [
            'modalidad_venta' => Producto::MODALIDAD_SOLO_PESO,
        ]);
    }
}
