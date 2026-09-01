<?php

namespace Database\Factories;

use App\Models\RestauracionRespaldo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestauracionRespaldo>
 */
class RestauracionRespaldoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operacion_uuid' => fake()->unique()->uuid(),
            'respaldo_nombre' => 'avicola-origen.sql',
            'respaldo_ruta' => 'respaldos/mysql/avicola-origen.sql',
            'respaldo_preventivo_nombre' => 'avicola-preventivo.sql',
            'respaldo_preventivo_ruta' => 'respaldos/mysql/avicola-preventivo.sql',
            'estado' => RestauracionRespaldo::ESTADO_COMPLETADA,
            'solicitado_por' => Usuario::factory(),
            'solicitante' => fake()->name(),
            'iniciado_at' => now()->subMinute(),
            'completado_at' => now(),
            'error' => null,
        ];
    }
}
