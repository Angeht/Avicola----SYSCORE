<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdministradorOperativoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::table('permisos')->updateOrInsert(
                ['codigo' => 'CONFIGURACION_EMPRESA_GESTIONAR'],
                [
                    'nombre' => 'Gestionar configuración de la empresa',
                    'descripcion' => 'Modificar los datos legales, comerciales y el mensaje de los comprobantes.',
                ],
            );
            DB::table('permisos')->updateOrInsert(
                ['codigo' => 'TIPOS_JABA_GESTIONAR'],
                [
                    'nombre' => 'Gestionar tipos de jaba y taras',
                    'descripcion' => 'Crear, editar, activar y desactivar tipos de jaba y sus taras referenciales.',
                ],
            );

            $operationalRoleId = DB::table('roles')
                ->where('nombre', 'ADMINISTRADOR OPERATIVO')
                ->value('id');

            if ($operationalRoleId === null) {
                $operationalRoleId = DB::table('roles')->insertGetId([
                    'nombre' => 'ADMINISTRADOR OPERATIVO',
                    'descripcion' => 'Control operativo y administrativo sin configuración general ni respaldos.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('roles')
                    ->where('id', $operationalRoleId)
                    ->update([
                        'descripcion' => 'Control operativo y administrativo sin configuración general ni respaldos.',
                        'activo' => true,
                        'updated_at' => now(),
                    ]);
            }

            $excludedCodes = [
                'CONFIGURACION_EMPRESA_GESTIONAR',
                'TIPOS_JABA_GESTIONAR',
                'RESPALDOS_GESTIONAR',
            ];
            $operationalPermissionIds = DB::table('permisos')
                ->whereNotIn('codigo', $excludedCodes)
                ->orderBy('id')
                ->pluck('id');

            DB::table('rol_permiso')->where('rol_id', $operationalRoleId)->delete();
            DB::table('rol_permiso')->insert(
                $operationalPermissionIds
                    ->map(fn (mixed $permissionId): array => [
                        'rol_id' => $operationalRoleId,
                        'permiso_id' => $permissionId,
                    ])
                    ->all(),
            );

            $administratorRoleId = DB::table('roles')
                ->where('nombre', 'ADMINISTRADOR')
                ->value('id');

            if ($administratorRoleId !== null) {
                DB::table('rol_permiso')->where('rol_id', $administratorRoleId)->delete();
                DB::table('rol_permiso')->insert(
                    DB::table('permisos')
                        ->orderBy('id')
                        ->pluck('id')
                        ->map(fn (mixed $permissionId): array => [
                            'rol_id' => $administratorRoleId,
                            'permiso_id' => $permissionId,
                        ])
                        ->all(),
                );
            }
        });
    }
}
