<?php

namespace Database\Factories;

use App\Models\TipoAjusteMercaderia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoAjusteMercaderia>
 */
class TipoAjusteMercaderiaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => mb_strtoupper(fake()->unique()->bothify('AJUSTE_##??')),
            'nombre' => mb_strtoupper(fake()->unique()->words(2, true)),
            'naturaleza' => 'ENTRADA',
            'requiere_motivo' => true,
            'activo' => true,
        ];
    }

    public function salida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'naturaleza' => 'SALIDA',
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }
}
