<?php

namespace Database\Factories;

use App\Models\Respaldo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Respaldo>
 */
class RespaldoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_archivo' => 'avicola-'.fake()->unique()->numerify('##############').'.sql',
            'disco' => 'local',
            'ruta' => 'respaldos/mysql/avicola-'.fake()->unique()->uuid().'.sql',
            'tipo' => Respaldo::TIPO_MANUAL,
            'estado' => Respaldo::ESTADO_COMPLETADO,
            'tamano_bytes' => 1024,
            'checksum_sha256' => hash('sha256', fake()->unique()->uuid()),
            'creado_por' => Usuario::factory(),
            'creado_at' => now(),
            'verificado_at' => null,
            'verificado_por' => null,
            'eliminado_at' => null,
            'eliminado_por' => null,
            'error' => null,
        ];
    }

    public function automatico(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo' => Respaldo::TIPO_AUTOMATICO,
            'creado_por' => null,
        ]);
    }

    public function verificado(?Usuario $usuario = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'verificado_at' => now(),
            'verificado_por' => $usuario?->getKey() ?? Usuario::factory(),
        ]);
    }

    public function fallido(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => Respaldo::ESTADO_FALLIDO,
            'tamano_bytes' => null,
            'checksum_sha256' => null,
            'error' => 'No fue posible crear la copia.',
        ]);
    }
}
