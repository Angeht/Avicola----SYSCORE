<?php

namespace Database\Factories;

use App\Models\CargaProveedor;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CargaProveedor>
 */
class CargaProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_carga' => 'CAR-'.fake()->unique()->numerify('########-######'),
            'proveedor_id' => Proveedor::factory(),
            'producto_id' => Producto::factory(),
            'fecha_carga' => today(),
            'costo_kg' => fake()->randomFloat(4, 1, 20),
            'costo_total' => fake()->randomFloat(2, 100, 10000),
            'recibido_por' => Usuario::factory(),
            'observacion' => fake()->optional()->sentence(),
            'anulada_por' => null,
            'anulada_at' => null,
            'motivo_anulacion' => null,
        ];
    }

    public function anulada(?Usuario $usuario = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'anulada_por' => $usuario?->getKey() ?? Usuario::factory(),
            'anulada_at' => now(),
            'motivo_anulacion' => 'Carga anulada para la prueba.',
        ]);
    }
}
