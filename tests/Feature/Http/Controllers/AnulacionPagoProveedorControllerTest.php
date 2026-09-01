<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\MedioPago;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AnulacionPagoProveedorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $payment = PagoProveedor::factory()->create();

        $this->get(route('pagos-proveedor.anulacion.create', $payment))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_cancellation_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();
        $payment = PagoProveedor::factory()->create();

        $this->actingAs($user)
            ->get(route('pagos-proveedor.anulacion.create', $payment))
            ->assertForbidden();
    }

    public function test_reason_is_required_and_must_have_at_least_ten_characters(): void
    {
        $user = $this->userWithPermission();
        $payment = PagoProveedor::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('pagos-proveedor.anulacion.create', $payment))
            ->post(route('pagos-proveedor.anulacion.store', $payment), [
                'motivo_anulacion' => 'Error',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'El motivo debe tener al menos 10 caracteres.',
        ]);
        $this->assertDatabaseHas('pagos_proveedor', [
            'id' => $payment->id,
            'anulada_at' => null,
        ]);
    }

    public function test_valid_cancellation_restores_load_balance_and_expected_cash(): void
    {
        $this->travelTo('2026-08-27 12:45:10');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-27',
            'apertura_at' => '2026-08-27 08:00:00',
            'monto_apertura' => 500,
        ]);
        $cashMethod = MedioPago::factory()->efectivo()->create();
        $load = CargaProveedor::factory()->create([
            'fecha_carga' => '2026-08-27',
            'costo_total' => 500,
        ]);
        $payment = PagoProveedor::factory()->create([
            'carga_id' => $load->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $cashMethod->id,
            'monto' => 125.25,
            'pagado_por' => $user->id,
            'pagado_at' => '2026-08-27 10:00:00',
        ]);

        $response = $this->actingAs($user)->post(route('pagos-proveedor.anulacion.store', $payment), [
            'motivo_anulacion' => '  importe   registrado por error ',
            'anulada_por' => $otherUser->id,
            'anulada_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('pagos-proveedor.show', $payment))
            ->assertSessionHas('status', "Pago {$payment->numero_pago} anulado correctamente.");
        $this->assertDatabaseHas('pagos_proveedor', [
            'id' => $payment->id,
            'anulada_por' => $user->id,
            'anulada_at' => '2026-08-27 12:45:10',
            'motivo_anulacion' => 'importe registrado por error',
        ]);
        $this->assertDatabaseMissing('pagos_proveedor', [
            'id' => $payment->id,
            'anulada_por' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('vw_saldos_carga_proveedor', [
            'carga_id' => $load->id,
            'total_pagado' => 0,
            'saldo_pendiente' => 500,
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'egresos_proveedor_efectivo' => 0,
            'efectivo_esperado' => 500,
        ]);
    }

    public function test_cancelled_payment_cannot_be_cancelled_again(): void
    {
        $user = $this->userWithPermission();
        $payment = PagoProveedor::factory()->anulado()->create();

        $response = $this->actingAs($user)
            ->from(route('pagos-proveedor.show', $payment))
            ->post(route('pagos-proveedor.anulacion.store', $payment), [
                'motivo_anulacion' => 'Segundo intento de anulación.',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'Este pago ya fue anulado.',
        ]);
    }

    public function test_payment_linked_to_closed_cash_session_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermission();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 500,
        ]);
        $cashMethod = MedioPago::factory()->efectivo()->create();
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);
        $payment = PagoProveedor::factory()->create([
            'carga_id' => $load->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $cashMethod->id,
            'monto' => 100,
            'pagado_por' => $user->id,
        ]);
        $cashSession->update([
            'cierre_at' => now(),
            'cerrada_por' => $user->id,
            'monto_contado_efectivo' => 400,
        ]);

        $this->actingAs($user)
            ->get(route('pagos-proveedor.anulacion.create', $payment))
            ->assertStatus(409);

        $response = $this->actingAs($user)
            ->from(route('pagos-proveedor.show', $payment))
            ->post(route('pagos-proveedor.anulacion.store', $payment), [
                'motivo_anulacion' => 'Corrección solicitada después del cierre.',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'No puedes anular un pago vinculado a una sesión de caja cerrada.',
        ]);
        $this->assertDatabaseHas('pagos_proveedor', [
            'id' => $payment->id,
            'anulada_at' => null,
        ]);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'PROVEEDORES_PAGO_ANULAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
