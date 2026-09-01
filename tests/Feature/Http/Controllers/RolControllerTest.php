<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RolControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_management_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_roles_and_rendered_values_are_escaped(): void
    {
        $actor = $this->userWithPermission();
        $dangerousDescription = 'Objetivo <script>alert(1)</script>';
        Rol::factory()->create(['nombre' => 'OBJETIVO', 'descripcion' => $dangerousDescription]);
        Rol::factory()->create(['nombre' => 'OCULTO']);

        $response = $this->actingAs($actor)->get(route('roles.index', [
            'buscar' => 'Objetivo',
            'estado' => 'todos',
        ]));

        $response
            ->assertOk()
            ->assertSee($dangerousDescription)
            ->assertDontSee($dangerousDescription, false)
            ->assertDontSee('OCULTO');
    }

    public function test_valid_payload_creates_normalized_active_role_with_permissions_and_audit(): void
    {
        $actor = $this->userWithPermission();
        $permission = Permiso::factory()->create([
            'codigo' => 'VENTAS_REGISTRAR',
            'nombre' => 'Registrar ventas',
        ]);
        $actor->roles()->firstOrFail()->permisos()->attach($permission);

        $response = $this->actingAs($actor)->post(route('roles.store'), [
            'nombre' => '  supervisor   comercial ',
            'descripcion' => '  Supervisa   ventas y caja ',
            'permisos' => [(string) $permission->id],
            'activo' => '0',
        ]);

        $response
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('status', 'Rol registrado correctamente.');

        $role = Rol::query()->where('nombre', 'SUPERVISOR COMERCIAL')->firstOrFail();

        $this->assertSame('Supervisa ventas y caja', $role->descripcion);
        $this->assertTrue($role->activo);
        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $role->id, 'permiso_id' => $permission->id]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $actor->id,
            'tabla_afectada' => 'roles',
            'registro_id' => $role->id,
            'accion' => 'INSERT',
        ]);
    }

    public function test_store_requires_name_and_at_least_one_permission(): void
    {
        $actor = $this->userWithPermission();

        $response = $this->actingAs($actor)
            ->from(route('roles.create'))
            ->post(route('roles.store'), []);

        $response
            ->assertRedirect(route('roles.create'))
            ->assertSessionHasErrors([
                'nombre' => 'Ingresa el nombre del rol.',
                'permisos' => 'Selecciona al menos un permiso.',
            ]);
        $this->assertDatabaseCount('roles', 1);
    }

    public function test_store_rejects_duplicate_name_and_permission(): void
    {
        $actor = $this->userWithPermission();
        Rol::factory()->create(['nombre' => 'SUPERVISOR']);
        $permission = Permiso::factory()->create(['codigo' => 'REPORTES_VER']);

        $response = $this->actingAs($actor)->post(route('roles.store'), [
            'nombre' => 'supervisor',
            'descripcion' => null,
            'permisos' => [$permission->id, $permission->id],
        ]);

        $response->assertSessionHasErrors([
            'nombre' => 'Ya existe un rol con ese nombre.',
            'permisos.1' => 'No puedes seleccionar el mismo permiso más de una vez.',
        ]);
        $this->assertDatabaseCount('roles', 2);
    }

    public function test_update_changes_role_and_permission_assignment_with_audit(): void
    {
        $actor = $this->userWithPermission();
        $oldPermission = Permiso::factory()->create(['codigo' => 'REPORTES_VER']);
        $newPermission = Permiso::factory()->create(['codigo' => 'AUDITORIA_VER']);
        $role = Rol::factory()->create([
            'nombre' => 'CONSULTA INICIAL',
            'descripcion' => 'Descripción anterior',
        ]);
        $role->permisos()->attach($oldPermission);
        $actor->roles()->firstOrFail()->permisos()->attach([$oldPermission->id, $newPermission->id]);

        $response = $this->actingAs($actor)->put(route('roles.update', $role), [
            'nombre' => ' auditor ',
            'descripcion' => ' Consulta   de trazabilidad ',
            'permisos' => [$newPermission->id],
            'activo' => '0',
        ]);

        $response
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('status', 'Rol actualizado correctamente.');

        $freshRole = $role->fresh();

        $this->assertSame('AUDITOR', $freshRole->nombre);
        $this->assertSame('Consulta de trazabilidad', $freshRole->descripcion);
        $this->assertTrue($freshRole->activo);
        $this->assertDatabaseMissing('rol_permiso', ['rol_id' => $role->id, 'permiso_id' => $oldPermission->id]);
        $this->assertDatabaseHas('rol_permiso', ['rol_id' => $role->id, 'permiso_id' => $newPermission->id]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'permisos',
            'valor_anterior' => 'REPORTES_VER',
            'valor_nuevo' => 'AUDITORIA_VER',
        ]);
    }

    public function test_administrator_role_keeps_name_and_all_permissions(): void
    {
        $administratorRole = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $actor = Usuario::factory()->create();
        $actor->roles()->attach($administratorRole);
        $salesPermission = Permiso::factory()->create(['codigo' => 'VENTAS_REGISTRAR']);
        $reportsPermission = Permiso::factory()->create(['codigo' => 'REPORTES_VER']);
        $administratorRole->permisos()->attach($salesPermission);

        $response = $this->actingAs($actor)->put(route('roles.update', $administratorRole), [
            'nombre' => 'RENOMBRADO',
            'descripcion' => 'Acceso estructural',
            'permisos' => [$salesPermission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertSame('ADMINISTRADOR', $administratorRole->fresh()->nombre);
        $this->assertDatabaseHas('rol_permiso', [
            'rol_id' => $administratorRole->id,
            'permiso_id' => $reportsPermission->id,
        ]);
        $this->assertSame(Permiso::query()->count(), $administratorRole->permisos()->count());
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'USUARIOS_GESTIONAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
