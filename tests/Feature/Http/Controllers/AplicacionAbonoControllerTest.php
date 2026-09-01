<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AplicacionAbonoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $collection = Cobranza::factory()->abono()->create();

        $this->get(route('cobranzas.aplicaciones.create', $collection))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_collection_permission_is_forbidden(): void
    {
        $collection = Cobranza::factory()->abono()->create();

        $this->actingAs(Usuario::factory()->create())
            ->get(route('cobranzas.aplicaciones.create', $collection))
            ->assertForbidden();
    }

    public function test_create_form_lists_only_pending_sales_for_the_abono_client(): void
    {
        $user = $this->userWithPermission();
        $client = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE DEL ABONO']);
        $otherClient = Cliente::factory()->create();
        $availableSale = Venta::factory()->conTotal(200)->create([
            'numero_venta' => 'VEN-20260828-000101',
            'cliente_id' => $client->id,
        ]);
        $alreadyAppliedSale = Venta::factory()->conTotal(100)->create([
            'numero_venta' => 'VEN-20260828-000202',
            'cliente_id' => $client->id,
        ]);
        $otherClientSale = Venta::factory()->conTotal(300)->create([
            'numero_venta' => 'VEN-20260828-000303',
            'cliente_id' => $otherClient->id,
        ]);
        $collection = Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'monto_total' => 150,
        ]);
        $collection->aplicaciones()->create([
            'venta_id' => $alreadyAppliedSale->id,
            'monto_aplicado' => 25,
        ]);

        $this->actingAs($user)
            ->get(route('cobranzas.aplicaciones.create', $collection))
            ->assertOk()
            ->assertSee('CLIENTE DEL ABONO')
            ->assertSee($availableSale->numero_venta)
            ->assertSee('value="'.$availableSale->id.'"', false)
            ->assertDontSee('value="'.$alreadyAppliedSale->id.'"', false)
            ->assertDontSee($otherClientSale->numero_venta);
    }

    public function test_valid_distribution_applies_existing_money_without_creating_another_collection(): void
    {
        $this->travelTo('2026-08-28 11:30:15');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $client = Cliente::factory()->create();
        $firstSale = Venta::factory()->conTotal(200)->create(['cliente_id' => $client->id]);
        $secondSale = Venta::factory()->conTotal(150)->create(['cliente_id' => $client->id]);
        $collection = Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'usuario_id' => $otherUser->id,
            'monto_total' => 250,
        ]);

        $response = $this->actingAs($user)->post(route('cobranzas.aplicaciones.store', $collection), [
            'aplicaciones' => [
                ['venta_id' => $firstSale->id, 'monto_aplicado' => '100,00'],
                ['venta_id' => $secondSale->id, 'monto_aplicado' => '50.00'],
            ],
            'usuario_id' => $otherUser->id,
            'monto_total' => 999,
        ]);

        $response
            ->assertRedirect(route('cobranzas.show', $collection))
            ->assertSessionHas('status', 'Se aplicaron S/ 150,00 del abono correctamente.');
        $this->assertDatabaseCount('cobranzas', 1);
        $this->assertDatabaseHas('cobranzas', [
            'id' => $collection->id,
            'usuario_id' => $otherUser->id,
            'monto_total' => 250,
            'anulada_at' => null,
        ]);
        $this->assertDatabaseHas('aplicacion_cobranzas', [
            'cobranza_id' => $collection->id,
            'venta_id' => $firstSale->id,
            'monto_aplicado' => 100,
        ]);
        $this->assertDatabaseHas('aplicacion_cobranzas', [
            'cobranza_id' => $collection->id,
            'venta_id' => $secondSale->id,
            'monto_aplicado' => 50,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $firstSale->id,
            'saldo_pendiente' => 100,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $secondSale->id,
            'saldo_pendiente' => 100,
        ]);
        $this->assertDatabaseHas('vw_cobranzas_pendientes_aplicar', [
            'cobranza_id' => $collection->id,
            'monto_aplicado' => 150,
            'monto_sin_aplicar' => 100,
        ]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'aplicacion_cobranzas',
            'registro_id' => $collection->id,
            'accion' => 'UPDATE',
            'created_at' => '2026-08-28 11:30:15',
        ]);
        $this->assertDatabaseCount('auditoria_detalles', 2);
    }

    public function test_distribution_cannot_exceed_the_available_abono_balance(): void
    {
        $user = $this->userWithPermission();
        $client = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => $client->id]);
        $collection = Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'monto_total' => 100,
        ]);

        $response = $this->actingAs($user)->post(route('cobranzas.aplicaciones.store', $collection), [
            'aplicaciones' => [[
                'venta_id' => $sale->id,
                'monto_aplicado' => '100.01',
            ]],
        ]);

        $response->assertSessionHasErrors([
            'aplicaciones' => 'El total aplicado supera el saldo disponible del abono.',
        ]);
        $this->assertDatabaseCount('aplicacion_cobranzas', 0);
        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_distribution_cannot_exceed_the_sale_balance(): void
    {
        $user = $this->userWithPermission();
        $client = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(100)->create(['cliente_id' => $client->id]);
        $collection = Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'monto_total' => 500,
        ]);

        $response = $this->actingAs($user)->post(route('cobranzas.aplicaciones.store', $collection), [
            'aplicaciones' => [[
                'venta_id' => $sale->id,
                'monto_aplicado' => '100.01',
            ]],
        ]);

        $response->assertSessionHasErrors([
            'aplicaciones.0.monto_aplicado' => 'El monto aplicado supera el saldo pendiente de la venta.',
        ]);
        $this->assertDatabaseCount('aplicacion_cobranzas', 0);
    }

    public function test_distribution_rejects_a_sale_from_another_client(): void
    {
        $user = $this->userWithPermission();
        $collectionClient = Cliente::factory()->create();
        $otherClient = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(100)->create(['cliente_id' => $otherClient->id]);
        $collection = Cobranza::factory()->abono()->create([
            'cliente_id' => $collectionClient->id,
            'monto_total' => 100,
        ]);

        $response = $this->actingAs($user)->post(route('cobranzas.aplicaciones.store', $collection), [
            'aplicaciones' => [[
                'venta_id' => $sale->id,
                'monto_aplicado' => 50,
            ]],
        ]);

        $response->assertSessionHasErrors([
            'aplicaciones.0.venta_id' => 'La venta seleccionada no pertenece al cliente del abono.',
        ]);
        $this->assertDatabaseCount('aplicacion_cobranzas', 0);
    }

    public function test_same_abono_cannot_be_applied_twice_to_the_same_sale(): void
    {
        $user = $this->userWithPermission();
        $client = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(200)->create(['cliente_id' => $client->id]);
        $collection = Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'monto_total' => 150,
        ]);
        $collection->aplicaciones()->create([
            'venta_id' => $sale->id,
            'monto_aplicado' => 50,
        ]);

        $response = $this->actingAs($user)->post(route('cobranzas.aplicaciones.store', $collection), [
            'aplicaciones' => [[
                'venta_id' => $sale->id,
                'monto_aplicado' => 25,
            ]],
        ]);

        $response->assertSessionHasErrors([
            'aplicaciones.0.venta_id' => 'Este abono ya fue aplicado a la venta seleccionada.',
        ]);
        $this->assertDatabaseCount('aplicacion_cobranzas', 1);
    }

    public function test_cancelled_collection_or_sale_payment_cannot_receive_later_applications(): void
    {
        $user = $this->userWithPermission();
        $salePayment = Cobranza::factory()->create();
        $cancelledAdvance = Cobranza::factory()->abono()->anulada()->create();

        $this->actingAs($user)
            ->get(route('cobranzas.aplicaciones.create', $salePayment))
            ->assertStatus(409);
        $this->actingAs($user)
            ->get(route('cobranzas.aplicaciones.create', $cancelledAdvance))
            ->assertStatus(409);

        $response = $this->actingAs($user)->post(route('cobranzas.aplicaciones.store', $salePayment), [
            'aplicaciones' => [],
        ]);

        $response->assertSessionHasErrors('aplicaciones');
    }

    public function test_application_rejects_unexpected_nested_fields(): void
    {
        $user = $this->userWithPermission();
        $client = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(100)->create(['cliente_id' => $client->id]);
        $collection = Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'monto_total' => 100,
        ]);

        $response = $this->actingAs($user)->post(route('cobranzas.aplicaciones.store', $collection), [
            'aplicaciones' => [[
                'venta_id' => $sale->id,
                'monto_aplicado' => 50,
                'usuario_id' => $user->id,
            ]],
        ]);

        $response->assertSessionHasErrors([
            'aplicaciones.0' => 'Una aplicación contiene campos no permitidos.',
        ]);
        $this->assertDatabaseCount('aplicacion_cobranzas', 0);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'COBRANZAS_REGISTRAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
