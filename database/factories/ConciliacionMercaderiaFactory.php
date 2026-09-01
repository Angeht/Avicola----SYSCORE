<?php

namespace Database\Factories;

use App\Models\ConciliacionMercaderia;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConciliacionMercaderia>
 */
class ConciliacionMercaderiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_conciliacion' => 'CON-'.fake()->unique()->numerify('########-######'),
            'producto_id' => Producto::factory(),
            'fecha_operacion' => now()->toDateString(),
            'tipo_conciliacion' => 'CIERRE',
            'usuario_id' => Usuario::factory(),
            'cantidad_pollos_sistema' => 100,
            'peso_sistema_kg' => 200,
            'cantidad_pollos_fisico' => 100,
            'peso_fisico_kg' => 200,
            'observacion' => fake()->optional()->sentence(),
            'realizada_at' => now(),
        ];
    }

    public function conDiferencia(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cantidad_pollos_fisico' => 95,
            'peso_fisico_kg' => 190,
        ]);
    }
}
