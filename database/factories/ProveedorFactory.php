<?php

namespace Database\Factories;

use App\Models\Proveedor;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_documento_id' => null,
            'nro_documento' => null,
            'nombre_razon_social' => fake()->company(),
            'telefono' => fake()->optional()->numerify('9########'),
            'numero_cuenta' => null,
            'direccion' => fake()->optional()->address(),
            'activo' => true,
        ];
    }

    public function conDocumento(?TipoDocumento $tipoDocumento = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo_documento_id' => $tipoDocumento?->getKey() ?? TipoDocumento::factory(),
            'nro_documento' => fake()->unique()->numerify('###########'),
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }
}
