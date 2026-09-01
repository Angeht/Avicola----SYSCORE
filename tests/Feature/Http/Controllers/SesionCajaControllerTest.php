<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SesionCajaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('caja.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_cash_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('caja.index'))
            ->assertForbidden();
    }

    public function test_valid_opening_creates_session_for_authenticated_user(): void
    {
        $this->travelTo('2026-08-27 07:45:30');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();

        $response = $this->actingAs($user)->post(route('caja.store'), [
            'monto_apertura' => '250.50',
            'usuario_id' => $otherUser->id,
            'fecha_operacion' => '2000-01-01',
            'cierre_at' => '2000-01-01 12:00:00',
        ]);

        $cashSession = SesionCaja::query()->firstOrFail();
        $response
            ->assertRedirect(route('caja.show', $cashSession))
            ->assertSessionHas('status', 'Apertura del día registrada correctamente. Ya puedes registrar operaciones.');
        $this->assertDatabaseHas('sesiones_caja', [
            'id' => $cashSession->id,
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-27',
            'apertura_at' => '2026-08-27 07:45:30',
            'monto_apertura' => 250.50,
            'cierre_at' => null,
        ]);
        $this->assertDatabaseMissing('sesiones_caja', ['usuario_id' => $otherUser->id]);
    }

    public function test_negative_opening_amount_is_rejected(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)
            ->from(route('caja.create'))
            ->post(route('caja.store'), ['monto_apertura' => '-0.01']);

        $response->assertSessionHasErrors([
            'monto_apertura' => 'El monto de apertura no puede ser negativo.',
        ]);
        $this->assertDatabaseCount('sesiones_caja', 0);
    }

    public function test_user_cannot_open_a_second_cash_session(): void
    {
        $user = $this->userWithPermission();
        SesionCaja::factory()->create(['usuario_id' => $user->id]);

        $response = $this->actingAs($user)
            ->from(route('caja.create'))
            ->post(route('caja.store'), ['monto_apertura' => '100.00']);

        $response->assertSessionHasErrors([
            'monto_apertura' => 'Ya tienes una sesión de caja abierta.',
        ]);
        $this->assertDatabaseCount('sesiones_caja', 1);
    }

    public function test_opening_form_preloads_previous_closing_cash_and_keeps_it_editable(): void
    {
        $this->travelTo('2026-08-30 07:30:00');
        $user = $this->userWithPermission();
        $olderSession = SesionCaja::factory()->cerrada()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-28',
            'apertura_at' => '2026-08-28 07:00:00',
            'cierre_at' => '2026-08-28 18:00:00',
            'monto_contado_efectivo' => 120.00,
        ]);
        $previousSession = SesionCaja::factory()->cerrada()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-29',
            'apertura_at' => '2026-08-29 07:00:00',
            'cierre_at' => '2026-08-29 18:00:00',
            'monto_contado_efectivo' => 342.75,
        ]);
        SesionCaja::factory()->cerrada()->create([
            'fecha_operacion' => '2026-08-29',
            'monto_contado_efectivo' => 999.99,
        ]);

        $this->actingAs($user)
            ->get(route('caja.create'))
            ->assertOk()
            ->assertSee("Jornada #{$previousSession->id}")
            ->assertDontSee("Jornada #{$olderSession->id}")
            ->assertSee('value="342.75"', false)
            ->assertSee('puedes corregirlo');

        $this->actingAs($user)->post(route('caja.store'), [
            'monto_apertura' => '400.00',
        ])->assertRedirect();

        $this->assertDatabaseHas('sesiones_caja', [
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-30',
            'monto_apertura' => 400.00,
            'cierre_at' => null,
        ]);
    }

    public function test_cash_history_only_lists_authenticated_users_sessions(): void
    {
        $this->travelTo('2026-08-27 08:00:00');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $ownSession = SesionCaja::factory()->cerrada()->create(['usuario_id' => $user->id]);
        $otherSession = SesionCaja::factory()->cerrada()->create(['usuario_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get(route('caja.index'));

        $response
            ->assertOk()
            ->assertSee("Jornada #{$ownSession->id}")
            ->assertDontSee("Jornada #{$otherSession->id}");
    }

    public function test_non_admin_user_cannot_view_another_users_cash_session(): void
    {
        $user = $this->userWithPermission();
        $otherSession = SesionCaja::factory()->create();

        $this->actingAs($user)
            ->get(route('caja.show', $otherSession))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_own_open_session_summary(): void
    {
        $user = $this->userWithPermission();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 175.25,
        ]);

        $this->actingAs($user)
            ->get(route('caja.show', $cashSession))
            ->assertOk()
            ->assertSee('Apertura del día activa')
            ->assertSee('S/ 175,25')
            ->assertSee('Ir al cierre del día');
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'CAJA_ABRIR_CERRAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
