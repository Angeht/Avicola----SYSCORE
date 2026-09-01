<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioPinAutorizacionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $administrator = $this->administrator();

        $this->get(route('usuarios.pin-autorizacion.edit', $administrator))
            ->assertRedirect(route('login'));
    }

    public function test_pin_screen_is_forbidden_for_a_non_administrator_target(): void
    {
        $administrator = $this->administrator();
        $target = Usuario::factory()->create();

        $this->actingAs($administrator)
            ->get(route('usuarios.pin-autorizacion.edit', $target))
            ->assertForbidden();
    }

    public function test_non_administrator_manager_cannot_configure_an_administrator_pin(): void
    {
        $manager = $this->userWithManagementPermission();
        $administrator = $this->administrator();

        $this->actingAs($manager)
            ->get(route('usuarios.pin-autorizacion.edit', $administrator))
            ->assertForbidden();
    }

    public function test_administrator_can_render_pin_screen_without_exposing_the_current_hash(): void
    {
        $administrator = $this->administrator('0427');
        $currentHash = $administrator->pin_autorizacion_hash;

        $this->actingAs($administrator)
            ->get(route('usuarios.pin-autorizacion.edit', $administrator))
            ->assertOk()
            ->assertSee('PIN administrativo')
            ->assertSee('Configurado')
            ->assertDontSee($currentHash);
    }

    public function test_administrator_can_store_hashed_pin_without_auditing_the_secret(): void
    {
        $administrator = $this->administrator();

        $response = $this->actingAs($administrator)
            ->put(route('usuarios.pin-autorizacion.update', $administrator), [
                'pin_autorizacion' => '0427',
                'pin_autorizacion_confirmation' => '0427',
            ]);

        $response
            ->assertRedirect(route('usuarios.edit', $administrator))
            ->assertSessionHas('status', "PIN administrativo de {$administrator->usuario} actualizado correctamente.");
        $this->assertTrue(Hash::check('0427', $administrator->fresh()->pin_autorizacion_hash));
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'pin_autorizacion_hash',
            'valor_anterior' => 'NO CONFIGURADO',
            'valor_nuevo' => 'CONFIGURADO',
        ]);
        $this->assertDatabaseMissing('auditoria_detalles', ['valor_nuevo' => '0427']);
    }

    public function test_pin_requires_exactly_four_digits_and_preserves_existing_pin(): void
    {
        $administrator = $this->administrator('0427');

        $response = $this->actingAs($administrator)
            ->put(route('usuarios.pin-autorizacion.update', $administrator), [
                'pin_autorizacion' => '12345',
                'pin_autorizacion_confirmation' => '12345',
            ]);

        $response->assertSessionHasErrors([
            'pin_autorizacion' => 'El PIN administrativo debe tener exactamente 4 dígitos.',
        ]);
        $this->assertTrue(Hash::check('0427', $administrator->fresh()->pin_autorizacion_hash));
        $this->assertDatabaseCount('auditorias', 0);
        $this->assertArrayNotHasKey('pin_autorizacion', session('_old_input', []));
        $this->assertArrayNotHasKey('pin_autorizacion_confirmation', session('_old_input', []));
    }

    private function administrator(?string $pin = null): Usuario
    {
        $role = Rol::factory()->create([
            'nombre' => 'ADMINISTRADOR',
            'activo' => true,
        ]);
        $administrator = Usuario::factory()->create([
            'pin_autorizacion_hash' => $pin,
        ]);
        $administrator->roles()->attach($role);

        return $administrator;
    }

    private function userWithManagementPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'USUARIOS_GESTIONAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
