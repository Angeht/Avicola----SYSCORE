<?php

namespace Database\Factories;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auditoria>
 */
class AuditoriaFactory extends Factory
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
            'tabla_afectada' => fake()->randomElement(['ventas', 'cobranzas', 'productos']),
            'registro_id' => fake()->numberBetween(1, 100),
            'accion' => fake()->randomElement(['INSERT', 'UPDATE', 'ANULAR']),
            'ip' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
