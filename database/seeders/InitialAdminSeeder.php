<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $username = config('avicola.initial_admin.username');
        $password = config('avicola.initial_admin.password');

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Configura INITIAL_ADMIN_USERNAME e INITIAL_ADMIN_PASSWORD antes de sembrar el administrador.');
        }

        $administrator = Usuario::query()->firstOrCreate(
            ['usuario' => $username],
            [
                'nombres' => 'Administrador',
                'apellidos' => 'Sistema',
                'password_hash' => $password,
                'activo' => true,
            ],
        );

        $administratorRole = Rol::query()
            ->where('nombre', 'ADMINISTRADOR')
            ->firstOrFail();

        $administrator->roles()->syncWithoutDetaching([$administratorRole->getKey()]);
    }
}
