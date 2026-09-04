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

class CobranzaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cobranzas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_collection_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('cobranzas.index'))
            ->assertForbidden();
    }

    public function test_collection_index_links_to_the_customer_debt_report_for_an_authorized_user(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $reportsPermission = Permiso::factory()->create(['codigo' => 'REPORTES_VER']);
        $user->roles()->firstOrFail()->permisos()->attach($reportsPermission);

        $this->actingAs($user)
            ->get(route('cobranzas.index'))
            ->assertOk()
            ->assertSee('Deudas por cliente')
            ->assertSee(route('reportes.show', 'cuentas-cobrar'), false);
    }

    public function test_collection_detail_links_to_the_selected_customers_debt_report(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $reportsPermission = Permiso::factory()->create(['codigo' => 'REPORTES_VER']);
        $user->roles()->firstOrFail()->permisos()->attach($reportsPermission);
        $client = Cliente::factory()->create();
        $collection = Cobranza::factory()->create([
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('cobranzas.show', $collection))
            ->assertOk()
            ->assertSee('Ver estado de cuenta')
            ->assertSee(route('reportes.customer-account', $client), false);
    }

    public function test_create_form_lists_only_clients_with_debt_and_does_not_request_sales(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $client = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE CON DEUDA']);
        $pendingSale = Venta::factory()->conTotal(200)->create([
            'numero_venta' => 'VEN-20260827-000101',
            'cliente_id' => $client->id,
        ]);
        $paidClient = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE SIN DEUDA']);
        $paidSale = Venta::factory()->conTotal(100)->create(['cliente_id' => $paidClient->id]);
        $collection = Cobranza::factory()->create([
            'cliente_id' => $paidClient->id,
            'usuario_id' => $user->id,
            'monto_total' => 100,
        ]);
        $collection->aplicaciones()->create([
            'venta_id' => $paidSale->id,
            'monto_aplicado' => 100,
        ]);
        $activeMethod = MedioPago::factory()->create(['nombre' => 'TRANSFERENCIA ACTIVA']);
        MedioPago::factory()->inactivo()->create(['nombre' => 'TRANSFERENCIA INACTIVA']);

        $response = $this->actingAs($user)->get(route('cobranzas.create', [
            'venta' => $pendingSale->id,
        ]));

        $response
            ->assertOk()
            ->assertSee($client->nombres_razon_social)
            ->assertSee('S/ 200,00')
            ->assertSee($activeMethod->nombre)
            ->assertSee('Aplicación automática')
            ->assertDontSee($pendingSale->numero_venta)
            ->assertDontSee($paidClient->nombres_razon_social)
            ->assertDontSee('Agregar venta')
            ->assertDontSee('TRANSFERENCIA INACTIVA');
    }

    public function test_partial_payment_is_automatically_applied_to_oldest_sales(): void
    {
        $this->travelTo('2026-08-27 16:15:30');
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $otherUser = Usuario::factory()->create();
        $client = Cliente::factory()->create();
        $oldestSale = Venta::factory()->conTotal(100)->create([
            'cliente_id' => $client->id,
            'fecha_venta' => '2026-08-25 10:00:00',
        ]);
        $newestSale = Venta::factory()->conTotal(200)->create([
            'cliente_id' => $client->id,
            'fecha_venta' => '2026-08-26 10:00:00',
        ]);
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-27',
            'apertura_at' => '2026-08-27 08:00:00',
            'monto_apertura' => 250,
        ]);
        $paymentMethod = MedioPago::factory()->create();

        $response = $this->actingAs($user)->post(route('cobranzas.store'), [
            'cliente_id' => $client->id,
            'medio_pago_id' => $paymentMethod->id,
            'monto_total' => '150,00',
            'observacion' => '  depósito   confirmado  ',
            'tipo' => 'ABONO',
            'numero_cobranza' => 'MANIPULADA',
            'usuario_id' => $otherUser->id,
            'fecha_pago' => '2000-01-01 00:00:00',
            'aplicaciones' => [[
                'venta_id' => $newestSale->id,
                'monto_aplicado' => 150,
                'usuario_id' => $otherUser->id,
            ]],
        ]);

        $collection = Cobranza::query()->firstOrFail();
        $expectedNumber = sprintf('COB-20260827-%06d', $collection->id);
        $response
            ->assertRedirect(route('cobranzas.show', $collection))
            ->assertSessionHas('status', "Cobranza $expectedNumber registrada correctamente.");
        $this->assertDatabaseHas('cobranzas', [
            'id' => $collection->id,
            'numero_cobranza' => $expectedNumber,
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $paymentMethod->id,
            'tipo' => 'PAGO_VENTA',
            'monto_total' => 150,
            'fecha_pago' => '2026-08-27 16:15:30',
            'observacion' => 'depósito confirmado',
            'anulada_at' => null,
        ]);
        $this->assertDatabaseMissing('cobranzas', ['usuario_id' => $otherUser->id]);
        $this->assertDatabaseHas('aplicacion_cobranzas', [
            'cobranza_id' => $collection->id,
            'venta_id' => $oldestSale->id,
            'monto_aplicado' => 100,
        ]);
        $this->assertDatabaseHas('aplicacion_cobranzas', [
            'cobranza_id' => $collection->id,
            'venta_id' => $newestSale->id,
            'monto_aplicado' => 50,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $oldestSale->id,
            'saldo_pendiente' => 0,
            'estado_pago' => 'SALDADA',
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $newestSale->id,
            'saldo_pendiente' => 150,
            'estado_pago' => 'PARCIAL',
        ]);
        $this->assertDatabaseHas('vw_saldos_cliente', [
            'cliente_id' => $client->id,
            'deuda_total' => 150,
        ]);
    }

    public function test_full_payment_settles_the_customers_complete_debt(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $client = Cliente::factory()->create();
        $firstSale = Venta::factory()->conTotal(125.50)->create(['cliente_id' => $client->id]);
        $secondSale = Venta::factory()->conTotal(74.50)->create(['cliente_id' => $client->id]);
        $paymentMethod = MedioPago::factory()->create();

        $this->actingAs($user)->post(route('cobranzas.store'), [
            'cliente_id' => $client->id,
            'medio_pago_id' => $paymentMethod->id,
            'monto_total' => '200.00',
            'observacion' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $firstSale->id,
            'saldo_pendiente' => 0,
            'estado_pago' => 'SALDADA',
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $secondSale->id,
            'saldo_pendiente' => 0,
            'estado_pago' => 'SALDADA',
        ]);
        $this->assertDatabaseHas('vw_saldos_cliente', [
            'cliente_id' => $client->id,
            'deuda_total' => 0,
            'ventas_pendientes' => 0,
        ]);
    }

    public function test_collection_rejects_an_amount_greater_than_the_customers_debt(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $client = Cliente::factory()->create();
        Venta::factory()->conTotal(200)->create(['cliente_id' => $client->id]);
        $paymentMethod = MedioPago::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('cobranzas.create'))
            ->post(route('cobranzas.store'), [
                'cliente_id' => $client->id,
                'medio_pago_id' => $paymentMethod->id,
                'monto_total' => '200.01',
                'observacion' => null,
            ]);

        $response->assertSessionHasErrors([
            'monto_total' => 'El monto recibido no puede superar la deuda actual del cliente.',
        ]);
        $this->assertDatabaseCount('cobranzas', 0);
        $this->assertDatabaseCount('aplicacion_cobranzas', 0);
    }

    public function test_collection_requires_a_customer(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $paymentMethod = MedioPago::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('cobranzas.create'))
            ->post(route('cobranzas.store'), [
                'cliente_id' => null,
                'medio_pago_id' => $paymentMethod->id,
                'monto_total' => '50.00',
            ]);

        $response->assertSessionHasErrors([
            'cliente_id' => 'Selecciona el cliente que realiza el pago.',
        ]);
        $this->assertDatabaseCount('cobranzas', 0);
    }

    public function test_collection_rejects_a_customer_without_current_debt(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $client = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(100)->create(['cliente_id' => $client->id]);
        $previousCollection = Cobranza::factory()->create([
            'cliente_id' => $client->id,
            'monto_total' => 100,
        ]);
        $previousCollection->aplicaciones()->create([
            'venta_id' => $sale->id,
            'monto_aplicado' => 100,
        ]);
        $paymentMethod = MedioPago::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('cobranzas.create'))
            ->post(route('cobranzas.store'), [
                'cliente_id' => $client->id,
                'medio_pago_id' => $paymentMethod->id,
                'monto_total' => '1.00',
            ]);

        $response->assertSessionHasErrors([
            'cliente_id' => 'El cliente ya no tiene deuda pendiente.',
        ]);
        $this->assertDatabaseCount('cobranzas', 1);
    }

    public function test_cash_collection_requires_an_open_session(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $client = Cliente::factory()->create();
        Venta::factory()->conTotal(200)->create(['cliente_id' => $client->id]);
        $cashMethod = MedioPago::factory()->efectivo()->create();

        $response = $this->actingAs($user)
            ->from(route('cobranzas.create'))
            ->post(route('cobranzas.store'), [
                'cliente_id' => $client->id,
                'medio_pago_id' => $cashMethod->id,
                'monto_total' => '100.00',
                'observacion' => null,
            ]);

        $response->assertSessionHasErrors([
            'medio_pago_id' => 'Abre una sesión de caja antes de registrar una cobranza en efectivo.',
        ]);
        $this->assertDatabaseCount('cobranzas', 0);
    }

    public function test_cash_collection_increases_expected_cash(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'monto_apertura' => 100,
        ]);
        $client = Cliente::factory()->create();
        Venta::factory()->conTotal(200)->create(['cliente_id' => $client->id]);
        $cashMethod = MedioPago::factory()->efectivo()->create();

        $this->actingAs($user)->post(route('cobranzas.store'), [
            'cliente_id' => $client->id,
            'medio_pago_id' => $cashMethod->id,
            'monto_total' => '75.25',
            'observacion' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('cobranzas', [
            'sesion_caja_id' => $cashSession->id,
            'monto_total' => 75.25,
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'ingresos_efectivo' => 75.25,
            'efectivo_esperado' => 175.25,
        ]);
    }

    public function test_cash_collection_can_close_a_few_cents_with_audited_rounding(): void
    {
        $this->travelTo('2026-09-02 10:30:00');
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $user->roles()->firstOrFail()->permisos()->attach(
            Permiso::query()->where('codigo', 'CLIENTES_AJUSTAR')->firstOrFail(),
        );
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-09-02',
            'monto_apertura' => 100,
        ]);
        $client = Cliente::factory()->create();
        $sale = Venta::factory()->conTotal(100)->create(['cliente_id' => $client->id]);
        $cashMethod = MedioPago::factory()->efectivo()->create();

        $this->actingAs($user)->post(route('cobranzas.store'), [
            'cliente_id' => $client->id,
            'medio_pago_id' => $cashMethod->id,
            'monto_total' => '99.95',
            'cerrar_por_redondeo' => '1',
        ])->assertRedirect();

        $collection = Cobranza::query()->firstOrFail();
        $this->assertDatabaseHas('ajustes_cliente', [
            'venta_id' => $sale->id,
            'cobranza_id' => $collection->id,
            'tipo' => 'REDONDEO',
            'monto' => 0.05,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $sale->id,
            'saldo_pendiente' => 0.00,
        ]);
        $this->assertDatabaseHas('vw_resumen_caja_usuario', [
            'sesion_caja_id' => $cashSession->id,
            'ingresos_efectivo' => 99.95,
            'efectivo_esperado' => 199.95,
        ]);
    }

    public function test_index_filters_collections_and_show_escapes_observations(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $matchingClient = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE EL SOL']);
        $otherClient = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE DEL NORTE']);
        $dangerousText = '<script>alert("cobranza")</script>';
        $matchingCollection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-20260827-000901',
            'cliente_id' => $matchingClient->id,
            'observacion' => $dangerousText,
        ]);
        $otherCollection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-20260827-000902',
            'cliente_id' => $otherClient->id,
        ]);

        $this->actingAs($user)
            ->get(route('cobranzas.index', ['buscar' => 'el sol']))
            ->assertOk()
            ->assertSee($matchingCollection->numero_cobranza)
            ->assertDontSee($otherCollection->numero_cobranza)
            ->assertDontSee('Deudas por cliente');

        $this->actingAs($user)
            ->get(route('cobranzas.show', $matchingCollection))
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
