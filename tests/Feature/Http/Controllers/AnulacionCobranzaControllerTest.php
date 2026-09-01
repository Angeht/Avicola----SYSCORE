<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\MedioPago;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AnulacionCobranzaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $collection = Cobranza::factory()->create();

        $this->get(route('cobranzas.anulacion.create', $collection))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_cancellation_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();
        $collection = Cobranza::factory()->create();

        $this->actingAs($user)
            ->get(route('cobranzas.anulacion.create', $collection))
            ->assertForbidden();
    }

    public function test_reason_is_required_and_must_have_at_least_ten_characters(): void
    {
        $user = $this->userWithPermission();
        $collection = Cobranza::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('cobranzas.anulacion.create', $collection))
            ->post(route('cobranzas.anulacion.store', $collection), [
                'motivo_anulacion' => 'Error',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'El motivo debe tener al menos 10 caracteres.',
        ]);
        $this->assertDatabaseHas('cobranzas', [
            'id' => $collection->id,
            'anulada_at' => null,
        ]);
    }

    public function test_valid_cancellation_restores_sale_balance_and_expected_cash(): void
    {
        $this->travelTo('2026-08-27 18:30:10');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $client = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => $client->id]);
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-27',
            'apertura_at' => '2026-08-27 08:00:00',
            'monto_apertura' => 100,
        ]);
        $cashMethod = MedioPago::factory()->efectivo()->create();
        $collection = Cobranza::factory()->create([
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $cashMethod->id,
            'monto_total' => 75,
            'fecha_pago' => '2026-08-27 17:00:00',
        ]);
        $collection->aplicaciones()->create([
            'venta_id' => $sale->id,
            'monto_aplicado' => 75,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $sale->id,
            'saldo_pendiente' => 125,
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'efectivo_esperado' => 175,
        ]);

        $response = $this->actingAs($user)->post(route('cobranzas.anulacion.store', $collection), [
            'motivo_anulacion' => '  ingreso   duplicado por error ',
            'anulada_por' => $otherUser->id,
            'anulada_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('cobranzas.show', $collection))
            ->assertSessionHas('status', "Cobranza {$collection->numero_cobranza} anulada correctamente.");
        $this->assertDatabaseHas('cobranzas', [
            'id' => $collection->id,
            'anulada_por' => $user->id,
            'anulada_at' => '2026-08-27 18:30:10',
            'motivo_anulacion' => 'ingreso duplicado por error',
        ]);
        $this->assertDatabaseMissing('cobranzas', [
            'id' => $collection->id,
            'anulada_por' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $sale->id,
            'total_pagado' => 0,
            'saldo_pendiente' => 200,
            'estado_pago' => 'PENDIENTE',
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'ingresos_efectivo' => 0,
            'efectivo_esperado' => 100,
        ]);
    }

    public function test_cancelled_collection_cannot_be_cancelled_again(): void
    {
        $user = $this->userWithPermission();
        $collection = Cobranza::factory()->anulada()->create();

        $response = $this->actingAs($user)
            ->from(route('cobranzas.show', $collection))
            ->post(route('cobranzas.anulacion.store', $collection), [
                'motivo_anulacion' => 'Segundo intento de anulación.',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'Esta cobranza ya fue anulada.',
        ]);
    }

    public function test_collection_linked_to_closed_cash_session_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermission();
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 100,
        ]);
        $cashMethod = MedioPago::factory()->efectivo()->create();
        $collection = Cobranza::factory()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $cashMethod->id,
            'monto_total' => 75,
        ]);
        $cashSession->update([
            'cierre_at' => now(),
            'cerrada_por' => $user->id,
            'monto_contado_efectivo' => 175,
        ]);

        $this->actingAs($user)
            ->get(route('cobranzas.anulacion.create', $collection))
            ->assertStatus(409);

        $response = $this->actingAs($user)
            ->from(route('cobranzas.show', $collection))
            ->post(route('cobranzas.anulacion.store', $collection), [
                'motivo_anulacion' => 'Intento posterior al cierre de caja.',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'No puedes anular una cobranza vinculada a una sesión de caja cerrada.',
        ]);
        $this->assertDatabaseHas('cobranzas', [
            'id' => $collection->id,
            'anulada_at' => null,
        ]);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'COBRANZAS_ANULAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
