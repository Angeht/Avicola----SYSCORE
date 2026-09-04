<?php

namespace Database\Factories;

use App\Models\AjusteProveedor;
use App\Models\CargaProveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AjusteProveedor>
 */
class AjusteProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_ajuste' => 'AJP-'.fake()->unique()->numerify('########-######'),
            'carga_id' => CargaProveedor::factory(),
            'pago_proveedor_id' => null,
            'ajuste_mercaderia_id' => null,
            'tipo' => 'DESCUENTO',
            'monto' => fake()->randomFloat(2, 1, 20),
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
