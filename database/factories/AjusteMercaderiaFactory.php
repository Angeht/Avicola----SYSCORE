<?php

namespace Database\Factories;

use App\Models\AjusteMercaderia;
use App\Models\Producto;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AjusteMercaderia>
 */
class AjusteMercaderiaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_ajuste' => 'AJU-'.fake()->unique()->numerify('########-######'),
            'producto_id' => Producto::factory(),
            'tipo_ajuste_id' => TipoAjusteMercaderia::factory(),
            'cantidad_pollos' => 10,
            'peso_kg' => 20,
            'motivo' => fake()->sentence(),
            'usuario_id' => Usuario::factory(),
            'fecha_ajuste' => now(),
            'anulado_por' => null,
            'anulado_at' => null,
            'motivo_anulacion' => null,
        ];
    }

    public function anulado(?Usuario $usuario = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'anulado_por' => $usuario?->getKey() ?? Usuario::factory(),
            'anulado_at' => now(),
            'motivo_anulacion' => 'Ajuste anulado para la prueba.',
        ]);
    }
}
