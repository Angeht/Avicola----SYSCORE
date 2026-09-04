<?php

namespace Tests\Feature\Security;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\AdministradorOperativoSeeder;
use Database\Seeders\AvicolaCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdministradorOperativoRoleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operational_administrator_has_daily_control_without_protected_configuration_access(): void
    {
        $this->seed(AvicolaCatalogSeeder::class);
        $this->seed(AdministradorOperativoSeeder::class);

        $role = Rol::query()
            ->where('nombre', 'ADMINISTRADOR OPERATIVO')
            ->with('permisos:id,codigo')
            ->firstOrFail();
        $protectedCodes = [
            'CONFIGURACION_EMPRESA_GESTIONAR',
            'RESPALDOS_GESTIONAR',
        ];

        $this->assertTrue($role->activo);
        $this->assertSame(
            Permiso::query()->count() - count($protectedCodes),
            $role->permisos->count(),
        );
        $this->assertTrue($role->permisos->contains('codigo', 'USUARIOS_GESTIONAR'));
        $this->assertTrue($role->permisos->contains('codigo', 'AUDITORIA_VER'));
        $this->assertTrue($role->permisos->contains('codigo', 'CARGAS_ANULAR'));
        $this->assertTrue($role->permisos->contains('codigo', 'VENTAS_EDITAR'));
        $this->assertTrue($role->permisos->contains('codigo', 'VENTAS_ANULAR'));
        $this->assertTrue($role->permisos->contains('codigo', 'TIPOS_JABA_GESTIONAR'));
        $cashRole = Rol::query()->where('nombre', 'CAJA')->with('permisos:id,codigo')->firstOrFail();
        $this->assertTrue($cashRole->permisos->contains('codigo', 'VENTAS_EDITAR'));
        $this->assertFalse($cashRole->permisos->contains('codigo', 'VENTAS_ANULAR'));
        $this->assertEmpty($role->permisos->pluck('codigo')->intersect($protectedCodes)->all());

        $actor = Usuario::factory()->create(['usuario' => 'admin.operativo']);
        $actor->roles()->attach($role);

        $this->actingAs($actor)->get(route('usuarios.index'))->assertOk();
        $this->actingAs($actor)->get(route('roles.index'))->assertOk();
        $this->actingAs($actor)->get(route('auditorias.index'))->assertOk();
        $this->actingAs($actor)->get(route('dashboard'))->assertOk();
        $this->actingAs($actor)->get(route('configuracion.edit'))->assertForbidden();
        $this->actingAs($actor)->get(route('tipos-jaba.index'))->assertOk();
        $this->actingAs($actor)->get(route('respaldos.index'))->assertForbidden();
    }

    public function test_builtin_roles_are_segmented_by_operational_responsibility(): void
    {
        $this->seed(AvicolaCatalogSeeder::class);
        $this->seed(AdministradorOperativoSeeder::class);

        $expectedPermissions = [
            'CAJA' => [
                'CAJA_ABRIR_CERRAR',
                'CLIENTES_AJUSTAR',
                'COBRANZAS_REGISTRAR',
                'PRECIO_VENTA_EDITAR',
                'PROVEEDORES_PAGAR',
                'REPORTES_VER',
                'VENTAS_EDITAR',
                'VENTAS_REGISTRAR',
            ],
            'OPERACIONES' => [
                'CARGAS_REGISTRAR',
                'MERCADERIA_AJUSTAR',
                'MERCADERIA_CONCILIAR',
                'PROVEEDORES_AJUSTAR',
                'REPORTES_VER',
                'TIPOS_JABA_GESTIONAR',
            ],
            'CONSULTA' => [
                'REPORTES_VER',
            ],
        ];

        foreach ($expectedPermissions as $roleName => $permissionCodes) {
            $actualPermissionCodes = Rol::query()
                ->where('nombre', $roleName)
                ->firstOrFail()
                ->permisos()
                ->orderBy('codigo')
                ->pluck('codigo')
                ->all();

            $this->assertSame($permissionCodes, $actualPermissionCodes, "Permisos incorrectos para {$roleName}.");
        }
    }

    public function test_operational_administrator_cannot_elevate_itself_or_manage_total_administrators(): void
    {
        $this->seed(AvicolaCatalogSeeder::class);
        $this->seed(AdministradorOperativoSeeder::class);

        $operationalRole = Rol::query()->where('nombre', 'ADMINISTRADOR OPERATIVO')->firstOrFail();
        $administratorRole = Rol::query()->where('nombre', 'ADMINISTRADOR')->firstOrFail();
        $backupPermission = Permiso::query()->where('codigo', 'RESPALDOS_GESTIONAR')->firstOrFail();
        $userManagementPermission = Permiso::query()->where('codigo', 'USUARIOS_GESTIONAR')->firstOrFail();
        $actor = Usuario::factory()->create(['usuario' => 'admin.operativo']);
        $actor->roles()->attach($operationalRole);
        $totalAdministrator = Usuario::factory()->create(['usuario' => 'admin.total']);
        $totalAdministrator->roles()->attach($administratorRole);

        $this->actingAs($actor)
            ->post(route('roles.store'), [
                'nombre' => 'ROL ELEVADO',
                'descripcion' => 'Intento de elevación',
                'permisos' => [$userManagementPermission->id, $backupPermission->id],
            ])
            ->assertSessionHasErrors([
                'permisos' => 'Los permisos de configuración y respaldos son exclusivos del rol ADMINISTRADOR.',
            ]);
        $this->assertDatabaseMissing('roles', ['nombre' => 'ROL ELEVADO']);

        $this->actingAs($actor)
            ->post(route('usuarios.store'), [
                'nombres' => 'Usuario',
                'apellidos' => 'Elevado',
                'usuario' => 'usuario.elevado',
                'password' => 'Clave#Segura2026',
                'password_confirmation' => 'Clave#Segura2026',
                'roles' => [$administratorRole->id],
            ])
            ->assertSessionHasErrors([
                'roles' => 'No puedes asignar un rol con permisos superiores a los tuyos.',
            ]);
        $this->assertDatabaseMissing('usuarios', ['usuario' => 'usuario.elevado']);

        $this->actingAs($actor)
            ->get(route('usuarios.edit', $totalAdministrator))
            ->assertForbidden();
        $this->actingAs($actor)
            ->get(route('usuarios.password.edit', $totalAdministrator))
            ->assertForbidden();
        $this->actingAs($actor)
            ->delete(route('usuarios.activacion.destroy', $totalAdministrator))
            ->assertForbidden();
        $this->actingAs($actor)
            ->get(route('roles.edit', $administratorRole))
            ->assertForbidden();
    }

    public function test_system_permissions_are_ignored_for_a_non_administrator_even_with_a_stale_assignment(): void
    {
        $role = Rol::factory()->create(['nombre' => 'ROL OBSOLETO']);
        $permissions = collect(Permiso::CODIGOS_EXCLUSIVOS_ADMINISTRADOR_SISTEMA)
            ->map(fn (string $code): Permiso => Permiso::query()->firstOrCreate(
                ['codigo' => $code],
                ['nombre' => $code],
            ));
        $permissionIds = $permissions->pluck('id')->all();
        $role->permisos()->attach($permissionIds);
        $actor = Usuario::factory()->create();
        $actor->roles()->attach($role);

        foreach (Permiso::CODIGOS_EXCLUSIVOS_ADMINISTRADOR_SISTEMA as $code) {
            $this->assertFalse($actor->tienePermiso($code));
        }

        $this->assertEmpty(array_intersect(
            $permissionIds,
            $actor->idsPermisosConcedibles(),
        ));
        $this->actingAs($actor)->get(route('configuracion.edit'))->assertForbidden();
        $this->actingAs($actor)->get(route('respaldos.index'))->assertForbidden();
    }
}
