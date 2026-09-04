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

            $systemAdministratorOnlyCodes = [
                'CONFIGURACION_EMPRESA_GESTIONAR',
                'RESPALDOS_GESTIONAR',
            ];
            $operationalPermissionIds = DB::table('permisos')
                ->whereNotIn('codigo', $systemAdministratorOnlyCodes)
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

            $this->syncRolePermissions('CAJA', [
                'VENTAS_REGISTRAR',
                'VENTAS_EDITAR',
                'PRECIO_VENTA_EDITAR',
                'COBRANZAS_REGISTRAR',
                'CLIENTES_AJUSTAR',
                'PROVEEDORES_PAGAR',
                'CAJA_ABRIR_CERRAR',
                'REPORTES_VER',
            ], 'Ventas, cobranzas, pagos a proveedores y cierre de caja.');
            $this->syncRolePermissions('OPERACIONES', [
                'CARGAS_REGISTRAR',
                'PROVEEDORES_AJUSTAR',
                'MERCADERIA_AJUSTAR',
                'MERCADERIA_CONCILIAR',
                'TIPOS_JABA_GESTIONAR',
                'REPORTES_VER',
            ], 'Cargas, pesajes, jabas e inventario, sin movimientos de dinero.');
            $this->syncRolePermissions('CONSULTA', [
                'REPORTES_VER',
            ], 'Consulta de reportes sin modificar operaciones.');
        });
    }

    /** @param list<string> $permissionCodes */
    private function syncRolePermissions(string $roleName, array $permissionCodes, string $description): void
    {
        $roleId = DB::table('roles')->where('nombre', $roleName)->value('id');

        if ($roleId === null) {
            return;
        }

        DB::table('roles')->where('id', $roleId)->update([
            'descripcion' => $description,
            'updated_at' => now(),
        ]);
        DB::table('rol_permiso')->where('rol_id', $roleId)->delete();

        $permissionIds = DB::table('permisos')
            ->whereIn('codigo', $permissionCodes)
            ->orderBy('id')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('rol_permiso')->insert(
                $permissionIds
                    ->map(fn (mixed $permissionId): array => [
                        'rol_id' => $roleId,
                        'permiso_id' => $permissionId,
                    ])
                    ->all(),
            );
        }
    }
}
