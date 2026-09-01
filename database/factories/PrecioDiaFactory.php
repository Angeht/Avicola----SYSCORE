<?php

namespace Database\Factories;

use App\Models\PrecioDia;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrecioDia>
 */
class PrecioDiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'fecha' => today(),
        ];
    }
}
