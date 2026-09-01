<?php

namespace Database\Factories;

use App\Models\AplicacionCobranza;
use App\Models\Cobranza;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AplicacionCobranza>
 */
class AplicacionCobranzaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cobranza_id' => Cobranza::factory(),
            'venta_id' => Venta::factory()->anonima()->conTotal(),
            'monto_aplicado' => 100,
        ];
    }
}
