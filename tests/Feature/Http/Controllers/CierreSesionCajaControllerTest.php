<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cobranza;
use App\Models\MedioPago;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CierreSesionCajaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $cashSession = SesionCaja::factory()->create();

        $this->get(route('caja.cierre.create', $cashSession))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_user_cannot_close_another_users_session(): void
    {
        $user = $this->userWithPermission();
        $otherSession = SesionCaja::factory()->create();

        $this->actingAs($user)
            ->post(route('caja.cierre.store', $otherSession), [
                'monto_contado_efectivo' => '100.00',
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('sesiones_caja', [
            'id' => $otherSession->id,
            'cierre_at' => null,
        ]);
    }

    public function test_owner_can_view_closing_form_with_expected_cash(): void
    {
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 125.75,
        ]);

        $this->actingAs($user)
            ->get(route('caja.cierre.create', $cashSession))
            ->assertOk()
            ->assertSee('Cierre del día')
            ->assertSee('S/ 125,75')
            ->assertSee($administrator->nombreCompleto())
            ->assertSee('PIN administrativo')
            ->assertSee('Confirmar cierre');
    }

    public function test_closing_form_breaks_down_all_payment_methods_and_includes_them_in_general_result(): void
    {
        $user = $this->userWithPermission();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 100.00,
        ]);
        $cash = MedioPago::factory()->efectivo()->create(['nombre' => 'Efectivo']);
        $yape = MedioPago::factory()->create(['nombre' => 'Yape']);
        $transfer = MedioPago::factory()->create(['nombre' => 'Transferencia bancaria']);

        Cobranza::factory()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $cash->id,
            'monto_total' => 50.00,
        ]);
        Cobranza::factory()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $yape->id,
            'monto_total' => 200.00,
        ]);
        Cobranza::factory()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $transfer->id,
            'monto_total' => 100.00,
        ]);
        PagoProveedor::factory()->create([
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $cash->id,
            'pagado_por' => $user->id,
            'monto' => 20.00,
        ]);
        PagoProveedor::factory()->create([
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $yape->id,
            'pagado_por' => $user->id,
            'monto' => 30.00,
        ]);
        PagoProveedor::factory()->create([
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $transfer->id,
            'pagado_por' => $user->id,
            'monto' => 10.00,
        ]);
        Cobranza::factory()->anulada()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $yape->id,
            'monto_total' => 999.00,
        ]);
        PagoProveedor::factory()->anulado()->create([
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $transfer->id,
            'pagado_por' => $user->id,
            'monto' => 999.00,
        ]);

        $response = $this->actingAs($user)
            ->get(route('caja.cierre.create', $cashSession));

        $response
            ->assertOk()
            ->assertSee('Resumen por medio de pago')
            ->assertSeeInOrder(['Efectivo', 'S/ 50,00', 'S/ 20,00', 'S/ 30,00'])
            ->assertSeeInOrder(['Transferencia bancaria', 'S/ 100,00', 'S/ 10,00', 'S/ 90,00'])
            ->assertSeeInOrder(['Yape', 'S/ 200,00', 'S/ 30,00', 'S/ 170,00'])
            ->assertSee('Resultado general del día')
            ->assertSee('S/ 390,00')
            ->assertDontSee('S/ 999,00');
    }

    public function test_balanced_count_closes_session_with_authenticated_responsible(): void
    {
        $this->travelTo('2026-08-27 18:15:00');
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $otherUser = Usuario::factory()->create();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-27',
            'apertura_at' => '2026-08-27 07:30:00',
            'monto_apertura' => 150.00,
        ]);

        $response = $this->actingAs($user)->post(route('caja.cierre.store', $cashSession), [
            'administrador_id' => $administrator->id,
            'pin_autorizacion' => '0427',
            'monto_contado_efectivo' => '150.00',
            'observacion_cierre' => null,
            'cerrada_por' => $otherUser->id,
            'cierre_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('caja.show', $cashSession))
            ->assertSessionHas('status', 'Cierre del día registrado correctamente. El arqueo quedó guardado.');
        $this->assertDatabaseHas('sesiones_caja', [
            'id' => $cashSession->id,
            'cierre_at' => '2026-08-27 18:15:00',
            'cerrada_por' => $user->id,
            'cierre_autorizada_por' => $administrator->id,
            'monto_contado_efectivo' => 150.00,
            'observacion_cierre' => null,
        ]);
        $this->assertDatabaseMissing('sesiones_caja', [
            'id' => $cashSession->id,
            'cerrada_por' => $otherUser->id,
        ]);
    }

    public function test_difference_requires_an_explanation_and_keeps_session_open(): void
    {
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 100.00,
        ]);

        $response = $this->actingAs($user)
            ->from(route('caja.cierre.create', $cashSession))
            ->post(route('caja.cierre.store', $cashSession), [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => '0427',
                'monto_contado_efectivo' => '95.00',
                'observacion_cierre' => '',
            ]);

        $response->assertSessionHasErrors([
            'observacion_cierre' => 'Explica la diferencia encontrada en el efectivo.',
        ]);
        $this->assertDatabaseHas('sesiones_caja', [
            'id' => $cashSession->id,
            'cierre_at' => null,
            'monto_contado_efectivo' => null,
        ]);
    }

    public function test_explained_difference_is_preserved_in_cash_summary(): void
    {
        $this->travelTo('2026-08-27 18:30:00');
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-27',
            'apertura_at' => '2026-08-27 08:00:00',
            'monto_apertura' => 100.00,
        ]);

        $this->actingAs($user)->post(route('caja.cierre.store', $cashSession), [
            'administrador_id' => $administrator->id,
            'pin_autorizacion' => '0427',
            'monto_contado_efectivo' => '95.00',
            'observacion_cierre' => '  faltante   <script>alert("caja")</script> ',
        ])->assertRedirect(route('caja.show', $cashSession));

        $this->assertDatabaseHas('sesiones_caja', [
            'id' => $cashSession->id,
            'monto_contado_efectivo' => 95.00,
            'observacion_cierre' => 'faltante <script>alert("caja")</script>',
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'efectivo_esperado' => 100.00,
            'diferencia_efectivo' => -5.00,
        ]);
        $this->actingAs($user)
            ->get(route('caja.show', $cashSession))
            ->assertSee('<script>alert("caja")</script>')
            ->assertDontSee('<script>alert("caja")</script>', false);
    }

    public function test_closed_day_shows_final_result_with_cash_yape_and_transfer(): void
    {
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 100.00,
        ]);
        $yape = MedioPago::factory()->create(['nombre' => 'Yape cierre']);
        $transfer = MedioPago::factory()->create(['nombre' => 'Transferencia cierre']);
        Cobranza::factory()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $yape->id,
            'monto_total' => 150.00,
        ]);
        Cobranza::factory()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $transfer->id,
            'monto_total' => 75.00,
        ]);
        PagoProveedor::factory()->create([
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $yape->id,
            'pagado_por' => $user->id,
            'monto' => 25.00,
        ]);

        $this->actingAs($user)->post(route('caja.cierre.store', $cashSession), [
            'administrador_id' => $administrator->id,
            'pin_autorizacion' => '0427',
            'monto_contado_efectivo' => '100.00',
            'observacion_cierre' => null,
        ])->assertRedirect(route('caja.show', $cashSession));

        $this->actingAs($user)
            ->get(route('caja.show', $cashSession))
            ->assertOk()
            ->assertSee('Resultado final del día')
            ->assertSee('S/ 300,00')
            ->assertSee('Yape cierre')
            ->assertSee('Transferencia cierre');
    }

    public function test_closed_session_cannot_be_closed_again(): void
    {
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $cashSession = SesionCaja::factory()->cerrada()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 80.00,
            'monto_contado_efectivo' => 80.00,
        ]);

        $response = $this->actingAs($user)
            ->from(route('caja.show', $cashSession))
            ->post(route('caja.cierre.store', $cashSession), [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => '0427',
                'monto_contado_efectivo' => '80.00',
            ]);

        $response->assertSessionHasErrors([
            'monto_contado_efectivo' => 'Esta sesión de caja ya fue cerrada.',
        ]);
    }

    public function test_administrator_can_close_another_users_session(): void
    {
        $administrator = Usuario::factory()->create([
            'pin_autorizacion_hash' => '0427',
        ]);
        $administratorRole = Rol::factory()->create(['nombre' => 'ADMINISTRADOR']);
        $administrator->roles()->attach($administratorRole);
        $cashSession = SesionCaja::factory()->create(['monto_apertura' => 50.00]);

        $this->actingAs($administrator)->post(route('caja.cierre.store', $cashSession), [
            'administrador_id' => $administrator->id,
            'pin_autorizacion' => '0427',
            'monto_contado_efectivo' => '50.00',
        ])->assertRedirect(route('caja.show', $cashSession));

        $this->assertDatabaseHas('sesiones_caja', [
            'id' => $cashSession->id,
            'cerrada_por' => $administrator->id,
            'cierre_autorizada_por' => $administrator->id,
            'monto_contado_efectivo' => 50.00,
        ]);
    }

    public function test_wrong_administrator_pin_keeps_the_day_open_and_does_not_flash_the_pin(): void
    {
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 100.00,
        ]);

        $response = $this->actingAs($user)
            ->from(route('caja.cierre.create', $cashSession))
            ->post(route('caja.cierre.store', $cashSession), [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => '9999',
                'monto_contado_efectivo' => '100.00',
            ]);

        $response->assertSessionHasErrors([
            'pin_autorizacion' => 'El administrador o el PIN no son correctos.',
        ]);
        $this->assertDatabaseHas('sesiones_caja', [
            'id' => $cashSession->id,
            'cierre_at' => null,
            'cierre_autorizada_por' => null,
        ]);
        $this->assertArrayNotHasKey('pin_autorizacion', session('_old_input', []));
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

    private function administratorWithPin(string $pin = '0427'): Usuario
    {
        $administrator = Usuario::factory()->create([
            'pin_autorizacion_hash' => $pin,
        ]);
        $role = Rol::factory()->create([
            'nombre' => 'ADMINISTRADOR',
            'activo' => true,
        ]);
        $administrator->roles()->attach($role);

        return $administrator;
    }
}
