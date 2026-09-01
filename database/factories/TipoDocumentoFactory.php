<?php

namespace Database\Factories;

use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => mb_strtoupper(fake()->unique()->bothify('TD##')),
            'nombre' => fake()->unique()->words(3, true),
            'longitud_maxima' => 20,
            'activo' => true,
        ];
    }
}
