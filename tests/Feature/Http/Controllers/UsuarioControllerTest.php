<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('usuarios.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_management_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('usuarios.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_users_and_rendered_values_are_escaped(): void
    {
        $actor = $this->userWithPermission();
        $dangerousName = 'Objetivo <script>alert(1)</script>';
        Usuario::factory()->create(['nombres' => $dangerousName, 'apellidos' => 'Torres']);
        Usuario::factory()->create(['nombres' => 'Cuenta oculta', 'apellidos' => 'Pérez']);

        $response = $this->actingAs($actor)->get(route('usuarios.index', [
            'buscar' => 'Objetivo',
            'estado' => 'todos',
        ]));

        $response
            ->assertOk()
            ->assertSee($dangerousName)
            ->assertDontSee($dangerousName, false)
            ->assertDontSee('Cuenta oculta');
    }

    public function test_create_screen_lists_only_active_roles(): void
    {
        $actor = $this->userWithPermission();
        Rol::factory()->create(['nombre' => 'ROL ACTIVO']);
        Rol::factory()->inactivo()->create(['nombre' => 'ROL INACTIVO']);

        $this->actingAs($actor)
            ->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee('ROL ACTIVO')
            ->assertDontSee('ROL INACTIVO');
    }

    public function test_valid_payload_creates_normalized_active_user_with_hashed_password_roles_and_audit(): void
    {
        $actor = $this->userWithPermission();
        $role = Rol::factory()->create(['nombre' => 'OPERADOR DE PRUEBA']);

        $response = $this->actingAs($actor)->post(route('usuarios.store'), [
            'nombres' => '  Ana   María ',
            'apellidos' => '  Torres   Díaz ',
            'usuario' => ' ANA.OPS ',
            'password' => 'Clave#Segura2026',
            'password_confirmation' => 'Clave#Segura2026',
            'roles' => [(string) $role->id],
            'activo' => '0',
            'ultimo_acceso' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('status', 'Usuario registrado correctamente.');

        $createdUser = Usuario::query()->where('usuario', 'ana.ops')->firstOrFail();

        $this->assertSame('Ana María', $createdUser->nombres);
        $this->assertSame('Torres Díaz', $createdUser->apellidos);
        $this->assertTrue($createdUser->activo);
        $this->assertNull($createdUser->ultimo_acceso);
        $this->assertTrue(Hash::check('Clave#Segura2026', $createdUser->password_hash));
        $this->assertDatabaseHas('usuario_rol', ['usuario_id' => $createdUser->id, 'rol_id' => $role->id]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $actor->id,
            'tabla_afectada' => 'usuarios',
            'registro_id' => $createdUser->id,
            'accion' => 'INSERT',
        ]);
        $this->assertDatabaseMissing('auditoria_detalles', ['valor_nuevo' => 'Clave#Segura2026']);
    }

    public function test_store_requires_identity_password_and_at_least_one_role(): void
    {
        $actor = $this->userWithPermission();

        $response = $this->actingAs($actor)
            ->from(route('usuarios.create'))
            ->post(route('usuarios.store'), []);

        $response
            ->assertRedirect(route('usuarios.create'))
            ->assertSessionHasErrors([
                'nombres' => 'Ingresa los nombres del usuario.',
                'apellidos' => 'Ingresa los apellidos del usuario.',
                'usuario' => 'Ingresa un nombre de usuario.',
                'password' => 'Define una contraseña inicial.',
                'roles' => 'Asigna al menos un rol.',
            ]);
        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_store_rejects_duplicate_username_and_inactive_role(): void
    {
        $actor = $this->userWithPermission();
        Usuario::factory()->create(['usuario' => 'duplicado']);
        $inactiveRole = Rol::factory()->inactivo()->create();

        $response = $this->actingAs($actor)->post(route('usuarios.store'), [
            'nombres' => 'Usuario',
            'apellidos' => 'Duplicado',
            'usuario' => 'DUPLICADO',
            'password' => 'Clave#Segura2026',
            'password_confirmation' => 'Clave#Segura2026',
            'roles' => [$inactiveRole->id],
        ]);

        $response->assertSessionHasErrors([
            'usuario' => 'Ese nombre de usuario ya está registrado.',
            'roles.0' => 'Uno de los roles seleccionados no está disponible.',
        ]);
        $this->assertDatabaseCount('usuarios', 2);
    }

    public function test_update_changes_identity_and_roles_without_accepting_password_or_active_state(): void
    {
        $actor = $this->userWithPermission();
        $oldRole = Rol::factory()->create(['nombre' => 'ROL ANTERIOR']);
        $newRole = Rol::factory()->create(['nombre' => 'ROL NUEVO']);
        $target = Usuario::factory()->create([
            'usuario' => 'objetivo',
            'password_hash' => 'Clave#Original2026',
        ]);
        $target->roles()->attach($oldRole);

        $response = $this->actingAs($actor)->put(route('usuarios.update', $target), [
            'nombres' => '  María  ',
            'apellidos' => '  Operaciones  ',
            'usuario' => ' MARIA.OPS ',
            'roles' => [$newRole->id],
            'activo' => '0',
            'password_hash' => 'Clave#Inyectada2026',
        ]);

        $response
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('status', 'Usuario actualizado correctamente.');

        $freshTarget = $target->fresh();

        $this->assertSame('María', $freshTarget->nombres);
        $this->assertSame('Operaciones', $freshTarget->apellidos);
        $this->assertSame('maria.ops', $freshTarget->usuario);
        $this->assertTrue($freshTarget->activo);
        $this->assertTrue(Hash::check('Clave#Original2026', $freshTarget->password_hash));
        $this->assertDatabaseMissing('usuario_rol', ['usuario_id' => $target->id, 'rol_id' => $oldRole->id]);
        $this->assertDatabaseHas('usuario_rol', ['usuario_id' => $target->id, 'rol_id' => $newRole->id]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'roles',
            'valor_anterior' => 'ROL ANTERIOR',
            'valor_nuevo' => 'ROL NUEVO',
        ]);
    }

    public function test_non_administrator_cannot_modify_total_administrator(): void
    {
        $actor = $this->userWithPermission();
        $administratorRole = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $replacementRole = Rol::factory()->create(['nombre' => 'CONSULTA']);
        $administrator = Usuario::factory()->create(['usuario' => 'administrador.unico']);
        $administrator->roles()->attach($administratorRole);

        $response = $this->actingAs($actor)->put(route('usuarios.update', $administrator), [
            'nombres' => $administrator->nombres,
            'apellidos' => $administrator->apellidos,
            'usuario' => $administrator->usuario,
            'roles' => [$replacementRole->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('usuario_rol', [
            'usuario_id' => $administrator->id,
            'rol_id' => $administratorRole->id,
        ]);
    }

    public function test_administrator_cannot_remove_own_administrator_role(): void
    {
        $administratorRole = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $replacementRole = Rol::factory()->create(['nombre' => 'CONSULTA']);
        $administrator = Usuario::factory()->create();
        $administrator->roles()->attach($administratorRole);

        $response = $this->actingAs($administrator)->put(route('usuarios.update', $administrator), [
            'nombres' => $administrator->nombres,
            'apellidos' => $administrator->apellidos,
            'usuario' => $administrator->usuario,
            'roles' => [$replacementRole->id],
        ]);

        $response->assertSessionHasErrors([
            'roles' => 'No puedes retirar tu propio rol de administrador.',
        ]);
        $this->assertDatabaseHas('usuario_rol', [
            'usuario_id' => $administrator->id,
            'rol_id' => $administratorRole->id,
        ]);
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
