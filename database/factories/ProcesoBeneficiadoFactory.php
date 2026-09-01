<?php

namespace Database\Factories;

use App\Models\CargaProveedor;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcesoBeneficiado>
 */
class ProcesoBeneficiadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_proceso' => 'BEN-'.fake()->unique()->numerify('########-######'),
            'carga_proveedor_id' => CargaProveedor::factory(),
            'producto_destino_id' => Producto::factory()->soloPeso(),
            'cantidad_pollos' => 40,
            'peso_origen_kg' => 100.000,
            'peso_resultante_kg' => 78.000,
            'procesado_at' => now(),
            'procesado_por' => Usuario::factory(),
            'observacion' => fake()->optional()->sentence(),
            'anulado_por' => null,
            'anulado_at' => null,
            'motivo_anulacion' => null,
        ];
    }

    public function anulada(?Usuario $usuario = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'anulado_por' => $usuario?->getKey() ?? Usuario::factory(),
            'anulado_at' => now(),
            'motivo_anulacion' => 'Proceso anulado para la prueba.',
        ]);
    }
}
