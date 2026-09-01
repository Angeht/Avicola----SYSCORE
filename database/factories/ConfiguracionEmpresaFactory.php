<?php

namespace Database\Factories;

use App\Models\ConfiguracionEmpresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionEmpresa>
 */
class ConfiguracionEmpresaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'razon_social' => mb_strtoupper(fake()->company()),
            'nombre_comercial' => mb_strtoupper(fake()->company()),
            'tipo_documento_id' => null,
            'nro_documento' => null,
            'direccion' => fake()->address(),
            'telefono' => fake()->phoneNumber(),
            'mensaje_ticket' => 'Gracias por su compra.',
        ];
    }
}
