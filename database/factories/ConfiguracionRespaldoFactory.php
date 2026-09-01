<?php

namespace Database\Factories;

use App\Models\ConfiguracionRespaldo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionRespaldo>
 */
class ConfiguracionRespaldoFactory extends Factory
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
            'activo' => true,
            'frecuencia' => ConfiguracionRespaldo::FRECUENCIA_DIARIA,
            'hora' => '02:00:00',
            'dia_semana' => 1,
            'dia_mes' => 1,
            'retencion_cantidad' => 14,
            'verificar_automaticamente' => true,
            'actualizado_por' => null,
        ];
    }
}
