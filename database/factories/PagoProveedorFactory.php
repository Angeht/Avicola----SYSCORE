<?php

namespace Database\Factories;

use App\Models\CargaProveedor;
use App\Models\MedioPago;
use App\Models\PagoProveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagoProveedor>
 */
class PagoProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_pago' => 'PAG-'.fake()->unique()->numerify('########-######'),
            'carga_id' => CargaProveedor::factory(['costo_total' => 1000]),
            'sesion_caja_id' => null,
            'medio_pago_id' => MedioPago::factory(),
            'monto' => 100,
            'pagado_por' => Usuario::factory(),
            'pagado_at' => now(),
            'observacion' => fake()->optional()->sentence(),
            'anulada_por' => null,
            'anulada_at' => null,
            'motivo_anulacion' => null,
        ];
    }

    public function anulado(?Usuario $usuario = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'anulada_por' => $usuario?->getKey() ?? Usuario::factory(),
            'anulada_at' => now(),
            'motivo_anulacion' => 'Registro anulado para la prueba.',
        ]);
    }
}
