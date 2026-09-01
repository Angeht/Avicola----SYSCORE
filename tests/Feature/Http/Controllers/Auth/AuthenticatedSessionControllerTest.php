<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_view_login_screen(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Inicia turno')
            ->assertSee('name="usuario"', false);
    }

    public function test_active_user_can_log_in(): void
    {
        $user = Usuario::factory()->create([
            'usuario' => 'operador.prueba',
            'password_hash' => 'Clave#Segura2026',
        ]);

        $response = $this->post(route('login.store'), [
            'usuario' => ' operador.prueba ',
            'password' => 'Clave#Segura2026',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->ultimo_acceso);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'usuarios',
            'registro_id' => $user->id,
            'accion' => 'LOGIN',
        ]);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        Usuario::factory()->inactivo()->create([
            'usuario' => 'usuario.inactivo',
            'password_hash' => 'Clave#Segura2026',
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'usuario' => 'usuario.inactivo',
            'password' => 'Clave#Segura2026',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'usuario' => 'El usuario o la contraseña no son correctos.',
            ]);
        $this->assertGuest();
    }

    public function test_user_cannot_log_in_with_wrong_password(): void
    {
        Usuario::factory()->create([
            'usuario' => 'usuario.valido',
            'password_hash' => 'Clave#Segura2026',
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'usuario' => 'usuario.valido',
            'password' => 'incorrecta',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'usuario' => 'El usuario o la contraseña no son correctos.',
            ]);
        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
