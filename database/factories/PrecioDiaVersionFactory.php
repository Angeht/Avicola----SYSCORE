<?php

namespace Database\Factories;

use App\Models\PrecioDia;
use App\Models\PrecioDiaVersion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrecioDiaVersion>
 */
class PrecioDiaVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'precio_dia_id' => PrecioDia::factory(),
            'precio_kg' => fake()->randomFloat(2, 3, 20),
            'vigente_desde' => now(),
            'registrado_por' => Usuario::factory(),
            'motivo_cambio' => null,
        ];
    }
}
