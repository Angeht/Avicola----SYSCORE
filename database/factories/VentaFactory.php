<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\PrecioDia;
use App\Models\PrecioDiaVersion;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_venta' => 'VEN-'.fake()->unique()->numerify('########-######'),
            'cliente_id' => Cliente::factory(),
            'usuario_id' => Usuario::factory(),
            'sesion_caja_id' => null,
            'fecha_venta' => now(),
            'anulada_por' => null,
            'anulada_at' => null,
            'motivo_anulacion' => null,
            'observacion' => fake()->optional()->sentence(),
        ];
    }

    public function anonima(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cliente_id' => null,
        ]);
    }

    public function anulada(?Usuario $usuario = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'anulada_por' => $usuario?->getKey() ?? Usuario::factory(),
            'anulada_at' => now(),
            'motivo_anulacion' => 'Venta anulada para la prueba.',
        ]);
    }

    public function conTotal(float $total = 200): static
    {
        return $this->afterCreating(function (Venta $venta) use ($total): void {
            $priceDay = PrecioDia::factory()->create(['fecha' => $venta->fecha_venta->toDateString()]);
            $priceVersion = PrecioDiaVersion::factory()->create([
                'precio_dia_id' => $priceDay->getKey(),
                'precio_kg' => 10,
                'vigente_desde' => $venta->fecha_venta,
            ]);
            $detail = VentaDetalle::factory()->create([
                'venta_id' => $venta->getKey(),
                'precio_version_id' => $priceVersion->getKey(),
                'precio_aplicado_kg' => 10,
            ]);
            $detail->pesajes()->create([
                'tipo_pesaje' => 'DIRECTO',
                'tipo_jaba_id' => null,
                'cantidad_jabas' => 0,
                'cantidad_pollos' => max(1, (int) round($total / 20)),
                'peso_bruto_kg' => $total / 10,
                'tara_unitaria_aplicada_kg' => 0,
                'observacion' => null,
            ]);
        });
    }
}
