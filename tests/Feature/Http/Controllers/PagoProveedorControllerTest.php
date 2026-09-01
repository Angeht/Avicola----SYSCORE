<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\MedioPago;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PagoProveedorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('pagos-proveedor.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_payment_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('pagos-proveedor.index'))
            ->assertForbidden();
    }

    public function test_create_form_only_lists_pending_loads_and_active_payment_methods(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $pendingLoad = CargaProveedor::factory()->create([
            'numero_carga' => 'CAR-20260827-000101',
            'costo_total' => 1000,
        ]);
        $paidLoad = CargaProveedor::factory()->create([
            'numero_carga' => 'CAR-20260827-000202',
            'costo_total' => 250,
        ]);
        PagoProveedor::factory()->create([
            'carga_id' => $paidLoad->id,
            'monto' => 250,
        ]);
        $activeMethod = MedioPago::factory()->create(['nombre' => 'TRANSFERENCIA ACTIVA']);
        MedioPago::factory()->inactivo()->create(['nombre' => 'TRANSFERENCIA INACTIVA']);

        $response = $this->actingAs($user)->get(route('pagos-proveedor.create', [
            'carga' => $pendingLoad->id,
        ]));

        $response
            ->assertOk()
            ->assertSee($pendingLoad->numero_carga)
            ->assertSee('value="'.$pendingLoad->id.'"', false)
            ->assertSee($activeMethod->nombre)
            ->assertDontSee($paidLoad->numero_carga)
            ->assertDontSee('TRANSFERENCIA INACTIVA');
    }

    public function test_cancelled_load_is_not_available_and_cannot_receive_new_payments(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $cancelledLoad = CargaProveedor::factory()->anulada()->create([
            'numero_carga' => 'CAR-ANULADA-000001',
            'costo_total' => 500,
        ]);
        $paymentMethod = MedioPago::factory()->create();

        $this->actingAs($user)
            ->get(route('pagos-proveedor.create'))
            ->assertOk()
            ->assertDontSee('CAR-ANULADA-000001');

        $this->actingAs($user)
            ->from(route('pagos-proveedor.create'))
            ->post(route('pagos-proveedor.store'), [
                'carga_id' => $cancelledLoad->id,
                'medio_pago_id' => $paymentMethod->id,
                'monto' => '50.00',
            ])
            ->assertSessionHasErrors('carga_id');
        $this->assertDatabaseCount('pagos_proveedor', 0);
    }

    public function test_valid_non_cash_payment_is_created_with_server_audit_data(): void
    {
        $this->travelTo('2026-08-27 10:15:30');
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $otherUser = Usuario::factory()->create();
        $load = CargaProveedor::factory()->create([
            'fecha_carga' => '2026-08-27',
            'costo_total' => 1000,
        ]);
        $paymentMethod = MedioPago::factory()->create();

        $response = $this->actingAs($user)->post(route('pagos-proveedor.store'), [
            'carga_id' => $load->id,
            'medio_pago_id' => $paymentMethod->id,
            'monto' => '325,50',
            'observacion' => '  operación   bancaria  8842 ',
            'numero_pago' => 'MANIPULADO',
            'pagado_por' => $otherUser->id,
            'pagado_at' => '2000-01-01 00:00:00',
            'anulada_por' => $otherUser->id,
        ]);

        $payment = PagoProveedor::query()->firstOrFail();
        $expectedNumber = sprintf('PAG-20260827-%06d', $payment->id);
        $response
            ->assertRedirect(route('pagos-proveedor.show', $payment))
            ->assertSessionHas('status', "Pago $expectedNumber registrado correctamente.");
        $this->assertDatabaseHas('pagos_proveedor', [
            'id' => $payment->id,
            'numero_pago' => $expectedNumber,
            'carga_id' => $load->id,
            'sesion_caja_id' => null,
            'medio_pago_id' => $paymentMethod->id,
            'monto' => 325.50,
            'pagado_por' => $user->id,
            'pagado_at' => '2026-08-27 10:15:30',
            'observacion' => 'operación bancaria 8842',
            'anulada_at' => null,
        ]);
        $this->assertDatabaseMissing('pagos_proveedor', [
            'pagado_por' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('vw_saldos_carga_proveedor', [
            'carga_id' => $load->id,
            'total_pagado' => 325.50,
            'saldo_pendiente' => 674.50,
        ]);
    }

    public function test_payment_uses_authenticated_users_open_session_even_for_non_cash_method(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 200,
        ]);
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);
        $paymentMethod = MedioPago::factory()->create();

        $this->actingAs($user)->post(route('pagos-proveedor.store'), [
            'carga_id' => $load->id,
            'medio_pago_id' => $paymentMethod->id,
            'monto' => '50.00',
            'observacion' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('pagos_proveedor', [
            'carga_id' => $load->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $paymentMethod->id,
            'monto' => 50,
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'efectivo_esperado' => 200,
            'egresos_proveedor_otros_medios' => 50,
        ]);
    }

    public function test_cash_payment_requires_an_open_session(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);
        $cashMethod = MedioPago::factory()->efectivo()->create();

        $response = $this->actingAs($user)
            ->from(route('pagos-proveedor.create'))
            ->post(route('pagos-proveedor.store'), [
                'carga_id' => $load->id,
                'medio_pago_id' => $cashMethod->id,
                'monto' => '100.00',
                'observacion' => null,
            ]);

        $response->assertSessionHasErrors([
            'medio_pago_id' => 'Abre una sesión de caja antes de registrar un pago en efectivo.',
        ]);
        $this->assertDatabaseCount('pagos_proveedor', 0);
    }

    public function test_cash_payment_cannot_exceed_expected_cash(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 75,
        ]);
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);
        $cashMethod = MedioPago::factory()->efectivo()->create();

        $response = $this->actingAs($user)
            ->from(route('pagos-proveedor.create'))
            ->post(route('pagos-proveedor.store'), [
                'carga_id' => $load->id,
                'medio_pago_id' => $cashMethod->id,
                'monto' => '75.01',
                'observacion' => null,
            ]);

        $response->assertSessionHasErrors([
            'monto' => 'La caja no tiene efectivo suficiente para registrar este pago.',
        ]);
        $this->assertDatabaseCount('pagos_proveedor', 0);
    }

    public function test_valid_cash_payment_reduces_expected_cash(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 500,
        ]);
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);
        $cashMethod = MedioPago::factory()->efectivo()->create();

        $this->actingAs($user)->post(route('pagos-proveedor.store'), [
            'carga_id' => $load->id,
            'medio_pago_id' => $cashMethod->id,
            'monto' => '125.25',
            'observacion' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('pagos_proveedor', [
            'carga_id' => $load->id,
            'sesion_caja_id' => $cashSession->id,
            'monto' => 125.25,
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'egresos_proveedor_efectivo' => 125.25,
            'efectivo_esperado' => 374.75,
        ]);
    }

    public function test_payment_cannot_exceed_the_loads_remaining_balance(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $load = CargaProveedor::factory()->create(['costo_total' => 300]);
        PagoProveedor::factory()->create([
            'carga_id' => $load->id,
            'monto' => 200,
        ]);
        $paymentMethod = MedioPago::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('pagos-proveedor.create'))
            ->post(route('pagos-proveedor.store'), [
                'carga_id' => $load->id,
                'medio_pago_id' => $paymentMethod->id,
                'monto' => '100.01',
                'observacion' => null,
            ]);

        $response->assertSessionHasErrors([
            'monto' => 'El pago no puede superar el saldo pendiente de la carga.',
        ]);
        $this->assertDatabaseCount('pagos_proveedor', 1);
    }

    public function test_inactive_payment_method_is_rejected(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $load = CargaProveedor::factory()->create(['costo_total' => 300]);
        $paymentMethod = MedioPago::factory()->inactivo()->create();

        $response = $this->actingAs($user)
            ->from(route('pagos-proveedor.create'))
            ->post(route('pagos-proveedor.store'), [
                'carga_id' => $load->id,
                'medio_pago_id' => $paymentMethod->id,
                'monto' => '100.00',
                'observacion' => null,
            ]);

        $response->assertSessionHasErrors([
            'medio_pago_id' => 'El medio de pago seleccionado no está disponible.',
        ]);
        $this->assertDatabaseCount('pagos_proveedor', 0);
    }

    public function test_index_filters_payments_and_show_escapes_observations(): void
    {
        $user = $this->userWithPermission('PROVEEDORES_PAGAR');
        $matchingProvider = Proveedor::factory()->create(['nombre_razon_social' => 'GRANJA EL SOL']);
        $otherProvider = Proveedor::factory()->create(['nombre_razon_social' => 'AVES DEL NORTE']);
        $dangerousText = '<script>alert("pago")</script>';
        $matchingPayment = PagoProveedor::factory()->create([
            'numero_pago' => 'PAG-20260827-000901',
            'carga_id' => CargaProveedor::factory()->create([
                'proveedor_id' => $matchingProvider->id,
                'costo_total' => 500,
            ])->id,
            'monto' => 100,
            'observacion' => $dangerousText,
        ]);
        $otherPayment = PagoProveedor::factory()->create([
            'numero_pago' => 'PAG-20260827-000902',
            'carga_id' => CargaProveedor::factory()->create([
                'proveedor_id' => $otherProvider->id,
                'costo_total' => 500,
            ])->id,
            'monto' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('pagos-proveedor.index', ['buscar' => 'el sol']))
            ->assertOk()
            ->assertSee($matchingPayment->numero_pago)
            ->assertDontSee($otherPayment->numero_pago);

        $this->actingAs($user)
            ->get(route('pagos-proveedor.show', $matchingPayment))
            ->assertOk()
            ->assertSee($dangerousText)
            ->assertDontSee($dangerousText, false);
    }

    private function userWithPermission(string $permissionCode): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => $permissionCode]);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
