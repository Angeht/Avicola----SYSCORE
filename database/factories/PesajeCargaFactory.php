<?php

namespace Database\Factories;

use App\Models\CargaProveedor;
use App\Models\PesajeCarga;
use App\Models\TipoJaba;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PesajeCarga>
 */
class PesajeCargaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carga_id' => CargaProveedor::factory(),
            'tipo_jaba_id' => TipoJaba::factory(['tara_referencial_kg' => 2.200]),
            'cantidad_jabas' => 10,
            'cantidad_pollos' => 100,
            'peso_bruto_kg' => 250.000,
            'tara_unitaria_aplicada_kg' => 2.200,
            'observacion' => fake()->optional()->sentence(),
        ];
    }

    public function sinJabas(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'tara_unitaria_aplicada_kg' => 0,
        ]);
    }
}
