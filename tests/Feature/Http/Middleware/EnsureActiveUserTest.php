<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnsureActiveUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_user_can_continue_to_authenticated_routes(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.password.edit'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_authenticated_user_is_logged_out_and_redirected_to_login(): void
    {
        $user = Usuario::factory()->inactivo()->create();

        $response = $this->actingAs($user)
            ->get(route('profile.password.edit'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'usuario' => 'Tu cuenta está desactivada. Comunícate con un administrador.',
            ]);
        $this->assertGuest();
    }
}
