<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\MedioPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cobranza>
 */
class CobranzaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_cobranza' => 'COB-'.fake()->unique()->numerify('########-######'),
            'cliente_id' => null,
            'usuario_id' => Usuario::factory(),
            'sesion_caja_id' => null,
            'medio_pago_id' => MedioPago::factory(),
            'tipo' => 'PAGO_VENTA',
            'monto_total' => 100,
            'fecha_pago' => now(),
            'observacion' => fake()->optional()->sentence(),
            'anulada_por' => null,
            'anulada_at' => null,
            'motivo_anulacion' => null,
        ];
    }

    public function abono(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cliente_id' => Cliente::factory(),
            'tipo' => 'ABONO',
        ]);
    }

    public function anulada(?Usuario $usuario = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'anulada_por' => $usuario?->getKey() ?? Usuario::factory(),
            'anulada_at' => now(),
            'motivo_anulacion' => 'Cobranza anulada para la prueba.',
        ]);
    }
}
