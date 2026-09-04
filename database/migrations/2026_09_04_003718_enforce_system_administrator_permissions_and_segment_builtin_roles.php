<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const SYSTEM_ADMINISTRATOR_ONLY_CODES = [
        'CONFIGURACION_EMPRESA_GESTIONAR',
        'RESPALDOS_GESTIONAR',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $systemAdministratorOnlyPermissionIds = DB::table('permisos')
                ->whereIn('codigo', self::SYSTEM_ADMINISTRATOR_ONLY_CODES)
                ->pluck('id');
            $administratorRoleId = DB::table('roles')
                ->where('nombre', 'ADMINISTRADOR')
                ->value('id');

            if ($systemAdministratorOnlyPermissionIds->isNotEmpty()) {
                $query = DB::table('rol_permiso')
                    ->whereIn('permiso_id', $systemAdministratorOnlyPermissionIds);

                if ($administratorRoleId !== null) {
                    $query->where('rol_id', '!=', $administratorRoleId);
                }

                $query->delete();
            }

            if ($administratorRoleId !== null) {
                $this->syncRoleWithAllPermissions((int) $administratorRoleId);
            }

            $this->syncRoleWithAllPermissionsExcept(
                'ADMINISTRADOR OPERATIVO',
                self::SYSTEM_ADMINISTRATOR_ONLY_CODES,
                'Control operativo y administrativo sin configuración general ni respaldos.',
            );
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

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->syncRoleWithAllPermissionsExcept(
                'ADMINISTRADOR OPERATIVO',
                [
                    'CONFIGURACION_EMPRESA_GESTIONAR',
                    'TIPOS_JABA_GESTIONAR',
                    'RESPALDOS_GESTIONAR',
                ],
                'Control operativo y administrativo sin configuración general ni respaldos.',
            );
            $this->syncRolePermissions('CAJA', [
                'VENTAS_REGISTRAR',
                'VENTAS_EDITAR',
                'PRECIO_VENTA_EDITAR',
                'COBRANZAS_REGISTRAR',
                'CLIENTES_AJUSTAR',
                'CAJA_ABRIR_CERRAR',
                'REPORTES_VER',
            ], 'Ventas, cobranzas, abonos y cierre de caja.');
            $this->syncRolePermissions('OPERACIONES', [
                'CARGAS_REGISTRAR',
                'PROVEEDORES_PAGAR',
                'PROVEEDORES_AJUSTAR',
                'PROVEEDORES_PAGO_ANULAR',
                'MERCADERIA_AJUSTAR',
                'MERCADERIA_CONCILIAR',
                'REPORTES_VER',
            ], 'Registro de cargas, pesajes y proveedores.');
        });
    }

    private function syncRoleWithAllPermissions(int $roleId): void
    {
        $this->syncPermissionIds(
            $roleId,
            DB::table('permisos')->orderBy('id')->pluck('id')->all(),
        );
    }

    /** @param list<string> $excludedPermissionCodes */
    private function syncRoleWithAllPermissionsExcept(
        string $roleName,
        array $excludedPermissionCodes,
        string $description,
    ): void {
        $roleId = $this->roleId($roleName, $description);

        if ($roleId === null) {
            return;
        }

        $this->syncPermissionIds(
            $roleId,
            DB::table('permisos')
                ->whereNotIn('codigo', $excludedPermissionCodes)
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
    }

    /** @param list<string> $permissionCodes */
    private function syncRolePermissions(
        string $roleName,
        array $permissionCodes,
        string $description,
    ): void {
        $roleId = $this->roleId($roleName, $description);

        if ($roleId === null) {
            return;
        }

        $this->syncPermissionIds(
            $roleId,
            DB::table('permisos')
                ->whereIn('codigo', $permissionCodes)
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
    }

    private function roleId(string $roleName, string $description): ?int
    {
        $roleId = DB::table('roles')->where('nombre', $roleName)->value('id');

        if ($roleId === null) {
            return null;
        }

        DB::table('roles')->where('id', $roleId)->update([
            'descripcion' => $description,
            'updated_at' => now(),
        ]);

        return (int) $roleId;
    }

    /** @param list<int> $permissionIds */
    private function syncPermissionIds(int $roleId, array $permissionIds): void
    {
        DB::table('rol_permiso')->where('rol_id', $roleId)->delete();

        if ($permissionIds === []) {
            return;
        }

        DB::table('rol_permiso')->insert(
            collect($permissionIds)
                ->map(fn (mixed $permissionId): array => [
                    'rol_id' => $roleId,
                    'permiso_id' => (int) $permissionId,
                ])
                ->all(),
        );
    }
};
