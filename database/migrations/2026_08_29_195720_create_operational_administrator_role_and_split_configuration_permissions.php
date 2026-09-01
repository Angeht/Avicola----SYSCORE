<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
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

            $administratorRoleId = DB::table('roles')
                ->where('nombre', 'ADMINISTRADOR')
                ->value('id');

            if ($administratorRoleId === null) {
                return;
            }

            $this->syncRolePermissions((int) $administratorRoleId, $this->permissionIds());

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

            $this->syncRolePermissions(
                (int) $operationalRoleId,
                $this->permissionIds([
                    'CONFIGURACION_EMPRESA_GESTIONAR',
                    'TIPOS_JABA_GESTIONAR',
                    'RESPALDOS_GESTIONAR',
                ]),
            );
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $operationalRoleId = DB::table('roles')
                ->where('nombre', 'ADMINISTRADOR OPERATIVO')
                ->value('id');

            if ($operationalRoleId !== null
                && ! DB::table('usuario_rol')->where('rol_id', $operationalRoleId)->exists()) {
                DB::table('roles')->where('id', $operationalRoleId)->delete();
            }

            DB::table('permisos')
                ->whereIn('codigo', [
                    'CONFIGURACION_EMPRESA_GESTIONAR',
                    'TIPOS_JABA_GESTIONAR',
                ])
                ->delete();
        });
    }

    /**
     * @param  list<string>  $excludedCodes
     * @return list<int>
     */
    private function permissionIds(array $excludedCodes = []): array
    {
        return DB::table('permisos')
            ->when(
                $excludedCodes !== [],
                fn ($query) => $query->whereNotIn('codigo', $excludedCodes),
            )
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $permissionIds */
    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        DB::table('rol_permiso')->where('rol_id', $roleId)->delete();

        DB::table('rol_permiso')->insert(
            collect($permissionIds)
                ->map(fn (int $permissionId): array => [
                    'rol_id' => $roleId,
                    'permiso_id' => $permissionId,
                ])
                ->all(),
        );
    }
};
