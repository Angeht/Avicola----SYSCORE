<?php

namespace Tests\Feature\Http\Controllers\Profile;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_view_password_screen(): void
    {
        $this->get(route('profile.password.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_update_password(): void
    {
        $user = Usuario::factory()->create([
            'password_hash' => 'Clave#Anterior2026',
        ]);

        $response = $this->actingAs($user)
            ->from(route('profile.password.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'Clave#Anterior2026',
                'password' => 'Nueva#ClaveSegura2026',
                'password_confirmation' => 'Nueva#ClaveSegura2026',
            ]);

        $response
            ->assertRedirect(route('profile.password.edit'))
            ->assertSessionHas('status', 'Tu contraseña fue actualizada correctamente.');

        $freshUser = $user->fresh();

        $this->assertTrue(Hash::check('Nueva#ClaveSegura2026', $freshUser->password_hash));
        $this->assertFalse(Hash::check('Clave#Anterior2026', $freshUser->password_hash));
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'usuarios',
            'registro_id' => $user->id,
            'accion' => 'UPDATE',
        ]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'password_hash',
            'valor_anterior' => 'PROTEGIDA',
            'valor_nuevo' => 'ACTUALIZADA POR EL USUARIO',
        ]);
        $this->assertDatabaseMissing('auditoria_detalles', ['valor_nuevo' => 'Nueva#ClaveSegura2026']);
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = Usuario::factory()->create([
            'password_hash' => 'Clave#Anterior2026',
        ]);

        $response = $this->actingAs($user)
            ->from(route('profile.password.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'Clave#Equivocada2026',
                'password' => 'Nueva#ClaveSegura2026',
                'password_confirmation' => 'Nueva#ClaveSegura2026',
            ]);

        $response
            ->assertRedirect(route('profile.password.edit'))
            ->assertSessionHasErrors([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);

        $this->assertTrue(Hash::check('Clave#Anterior2026', $user->fresh()->password_hash));
    }
}
