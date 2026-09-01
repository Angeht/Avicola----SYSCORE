<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\ConfiguracionEmpresa;
use App\Models\MedioPago;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\PesajeVenta;
use App\Models\PrecioDia;
use App\Models\PrecioDiaVersion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReporteControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reportes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_reports_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();
        $client = Cliente::factory()->create();

        $this->actingAs($user)
            ->get(route('reportes.index'))
            ->assertForbidden();

        foreach ([
            route('reportes.customer-account', $client),
            route('reportes.customer-account.csv', $client),
            route('reportes.customer-account.print', $client),
            route('reportes.customer-account.ticket', $client),
        ] as $route) {
            $this->actingAs($user)
                ->get($route)
                ->assertForbidden();
        }
    }

    public function test_authorized_user_can_open_report_center_and_every_report(): void
    {
        $user = $this->userWithPermission('REPORTES_VER');

        $this->actingAs($user)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Centro de reportes')
            ->assertSee('Deudas y abonos por cliente')
            ->assertSee('Movimientos y valorización');

        foreach (['ventas', 'cuentas-cobrar', 'deudas-proveedores', 'mercaderia', 'caja'] as $report) {
            $this->actingAs($user)
                ->get(route('reportes.show', $report))
                ->assertOk()
                ->assertSee('Excel / CSV')
                ->assertSee('Imprimir / PDF');
        }
    }

    public function test_sales_report_filters_and_displays_sales_by_client_and_product(): void
    {
        $user = $this->userWithPermission('REPORTES_VER');
        [$sale, $client, $product] = $this->createSale($user, 'Cliente objetivo', 'POLLO OBJETIVO');
        [$hiddenSale] = $this->createSale($user, 'Cliente oculto', 'POLLO OCULTO');

        $response = $this->actingAs($user)->get(route('reportes.show', [
            'report' => 'ventas',
            'desde' => today()->toDateString(),
            'hasta' => today()->toDateString(),
            'cliente_id' => $client->id,
            'producto_id' => $product->id,
            'estado' => 'ACTIVA',
        ]));

        $response
            ->assertOk()
            ->assertSee($sale->numero_venta)
            ->assertSee('Cliente objetivo')
            ->assertSee('POLLO OBJETIVO')
            ->assertDontSee($hiddenSale->numero_venta);
    }

    public function test_customer_account_report_consolidates_sales_and_unapplied_advances(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithPermission('REPORTES_VER');
        [, $client] = $this->createSale($user, 'Cliente consolidado', 'POLLO ANTERIOR', '2026-08-28 10:00:00');
        [$currentSale] = $this->createSale($user, 'Cliente consolidado', 'POLLO ACTUAL', '2026-08-30 10:00:00', $client);
        Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'monto_total' => 20,
            'fecha_pago' => '2026-08-29 11:00:00',
        ]);
        Cobranza::factory()->abono()->create([
            'cliente_id' => $client->id,
            'monto_total' => 50,
            'fecha_pago' => '2026-08-30 11:00:00',
        ]);
        Cobranza::factory()->abono()->anulada()->create([
            'cliente_id' => $client->id,
            'monto_total' => 500,
            'fecha_pago' => '2026-08-30 11:30:00',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.show', [
            'report' => 'cuentas-cobrar',
            'desde' => '2026-08-30',
            'hasta' => '2026-08-30',
            'cliente_id' => $client->id,
            'estado' => 'PARCIAL',
        ]));

        $response
            ->assertOk()
            ->assertSee('Cliente consolidado')
            ->assertSee('S/ 340,00')
            ->assertSee('S/ 70,00')
            ->assertSee('S/ 270,00')
            ->assertSee('ventas realizadas, la deuda total generada, todos sus abonos')
            ->assertSee('Ver detalle')
            ->assertSee(route('reportes.customer-account', [
                'cliente' => $client,
                'hasta' => '2026-08-30',
            ]), false)
            ->assertDontSee($currentSale->numero_venta);

        $csv = $this->actingAs($user)->get(route('reportes.csv', [
            'report' => 'cuentas-cobrar',
            'desde' => '2026-08-30',
            'hasta' => '2026-08-30',
            'cliente_id' => $client->id,
        ]));

        $csv->assertOk()->assertDownload();
        $this->assertStringContainsString(
            'Cliente;"Ventas realizadas";"Total deuda";"Total abonado";"Restante por pagar";Cobros;"Último pago";Estado',
            $csv->streamedContent(),
        );
    }

    public function test_customer_account_detail_lists_sales_payments_and_running_balance_until_cutoff(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithPermission('REPORTES_VER');
        [$firstSale, $client] = $this->createSale($user, 'Cliente con estado detallado', 'POLLO VIVO', '2026-08-28 08:00:00');
        $firstCollection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-20260828-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 50,
            'fecha_pago' => '2026-08-28 12:00:00',
        ]);
        [$secondSale] = $this->createSale($user, 'Cliente con estado detallado', 'POLLO BENEFICIADO', '2026-08-29 09:00:00', $client);
        $secondCollection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-20260830-000002',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 70,
            'fecha_pago' => '2026-08-30 10:00:00',
        ]);
        $cancelledCollection = Cobranza::factory()->abono()->anulada($user)->create([
            'numero_cobranza' => 'COB-ANULADA-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 500,
            'fecha_pago' => '2026-08-30 11:00:00',
        ]);
        $futureCollection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-FUTURA-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 30,
            'fecha_pago' => '2026-08-31 09:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.customer-account', [
            'cliente' => $client,
            'hasta' => '2026-08-30',
        ]));

        $response
            ->assertOk()
            ->assertViewIs('reportes.customer-account')
            ->assertViewHas('status', 'PARCIAL')
            ->assertViewHas('summary', function (array $summary): bool {
                $this->assertSame([
                    'sales_count' => 2,
                    'total_sales' => 340,
                    'collections_count' => 2,
                    'total_collections' => 120,
                    'remaining' => 220,
                    'credit' => 0,
                ], $summary);

                return true;
            })
            ->assertViewHas('movements', function ($movements) use ($firstSale, $firstCollection, $secondSale, $secondCollection): bool {
                $rows = $movements->getCollection();

                $this->assertSame([
                    $firstSale->numero_venta,
                    $firstCollection->numero_cobranza,
                    $secondSale->numero_venta,
                    $secondCollection->numero_cobranza,
                ], $rows->pluck('documento')->all());
                $this->assertSame([170.0, 120.0, 290.0, 220.0], $rows
                    ->map(fn (object $row): float => (float) $row->saldo_acumulado)
                    ->all());

                return true;
            })
            ->assertSee('Estado de cuenta')
            ->assertSee('Cliente con estado detallado')
            ->assertSee('Total de ventas')
            ->assertSee('Total abonado')
            ->assertSee('Restante por pagar')
            ->assertSee(route('ventas.show', $firstSale), false)
            ->assertSee(route('cobranzas.show', $firstCollection), false)
            ->assertSee(route('reportes.customer-account.csv', [
                'cliente' => $client,
                'hasta' => '2026-08-30',
            ]), false)
            ->assertSee(route('reportes.customer-account.print', [
                'cliente' => $client,
                'hasta' => '2026-08-30',
            ]), false)
            ->assertSee(route('reportes.customer-account.ticket', [
                'cliente' => $client,
                'hasta' => '2026-08-30',
            ]), false)
            ->assertDontSee($cancelledCollection->numero_cobranza)
            ->assertDontSee($futureCollection->numero_cobranza);
    }

    public function test_customer_account_csv_download_contains_summary_and_all_movements(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithPermission('REPORTES_VER');
        [$sale, $client] = $this->createSale($user, 'Cliente exportable', '=SUM(1+1)', '2026-08-28 08:00:00');
        $collection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-EXPORTAR-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 50,
            'fecha_pago' => '2026-08-29 09:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.customer-account.csv', [
            'cliente' => $client,
            'hasta' => '2026-08-30',
        ]));

        $response
            ->assertOk()
            ->assertDownload('estado-de-cuenta-cliente-exportable-2026-08-30.csv')
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Cliente;"Cliente exportable"', $content);
        $this->assertStringContainsString('"Total de ventas";170.00', $content);
        $this->assertStringContainsString('"Total abonado";50.00', $content);
        $this->assertStringContainsString('"Restante por pagar";120.00', $content);
        $this->assertStringContainsString('Fecha;Movimiento;Documento;Detalle;"Venta (cargo)";"Abono (pago)";"Saldo acumulado"', $content);
        $this->assertStringContainsString($sale->numero_venta, $content);
        $this->assertStringContainsString($collection->numero_cobranza, $content);
        $this->assertStringContainsString("'=SUM(1+1)", $content);
    }

    public function test_customer_account_print_view_can_be_saved_as_pdf(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithPermission('REPORTES_VER');
        [$sale, $client] = $this->createSale($user, 'Cliente imprimible', 'POLLO VIVO', '2026-08-28 08:00:00');
        $collection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-IMPRIMIR-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 50,
            'fecha_pago' => '2026-08-29 09:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.customer-account.print', [
            'cliente' => $client,
            'hasta' => '2026-08-30',
        ]));

        $response
            ->assertOk()
            ->assertViewIs('reportes.customer-account-print')
            ->assertSee('Imprimir / Guardar como PDF')
            ->assertSee('Cliente imprimible')
            ->assertSee('S/ 170,00')
            ->assertSee('S/ 50,00')
            ->assertSee('S/ 120,00')
            ->assertSee($sale->numero_venta)
            ->assertSee($collection->numero_cobranza);
    }

    public function test_customer_account_ticket_prints_company_movements_and_remaining_balance(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithPermission('REPORTES_VER');
        ConfiguracionEmpresa::factory()->create([
            'nombre_comercial' => 'GRANJA DEL NORTE',
            'mensaje_ticket' => 'Gracias por su preferencia.',
        ]);
        [$sale, $client] = $this->createSale($user, 'Cliente con ticket', 'POLLO <script>alert("x")</script>', '2026-08-28 08:00:00');
        $collection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-TICKET-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 50,
            'fecha_pago' => '2026-08-29 09:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.customer-account.ticket', [
            'cliente' => $client,
            'hasta' => '2026-08-30',
        ]));

        $response
            ->assertOk()
            ->assertViewIs('reportes.customer-account-ticket')
            ->assertSee('GRANJA DEL NORTE')
            ->assertSee('Estado de cuenta')
            ->assertSee('Cliente con ticket')
            ->assertSee('Corte 30/08/2026')
            ->assertSee($sale->numero_venta)
            ->assertSee($collection->numero_cobranza)
            ->assertSee('Total ventas')
            ->assertSee('S/ 170,00')
            ->assertSee('S/ 50,00')
            ->assertSee('S/ 120,00')
            ->assertSee('Gracias por su preferencia.')
            ->assertSee('window.print()', false)
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_customer_account_ticket_starts_with_the_sale_after_the_last_zero_balance(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithPermission('REPORTES_VER');
        [$firstSettledSale, $client] = $this->createSale($user, 'Cliente por ciclos', 'POLLO CICLO UNO', '2026-08-20 08:00:00');
        [$secondSettledSale] = $this->createSale($user, 'Cliente por ciclos', 'POLLO CICLO DOS', '2026-08-21 08:00:00', $client);
        $settlement = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-CIERRE-CICLO-001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 340,
            'fecha_pago' => '2026-08-22 09:00:00',
        ]);
        [$currentSale] = $this->createSale($user, 'Cliente por ciclos', 'POLLO CICLO VIGENTE', '2026-08-23 08:00:00', $client);
        $currentCollection = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-CICLO-VIGENTE-001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 50,
            'fecha_pago' => '2026-08-24 09:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.customer-account.ticket', [
            'cliente' => $client,
            'hasta' => '2026-08-30',
        ]));

        $response
            ->assertOk()
            ->assertViewHas('cycleReset', true)
            ->assertViewHas('summary', [
                'sales_count' => 1,
                'total_sales' => 170,
                'collections_count' => 1,
                'total_collections' => 50,
                'remaining' => 120,
                'credit' => 0,
            ])
            ->assertSee('Este ticket comienza desde la venta siguiente')
            ->assertSee($currentSale->numero_venta)
            ->assertSee($currentCollection->numero_cobranza)
            ->assertDontSee($firstSettledSale->numero_venta)
            ->assertDontSee($secondSettledSale->numero_venta)
            ->assertDontSee($settlement->numero_cobranza);
    }

    public function test_customer_account_ticket_is_empty_after_the_current_cycle_is_fully_settled(): void
    {
        $this->travelTo('2026-08-30 12:00:00');
        $user = $this->userWithPermission('REPORTES_VER');
        [$settledSale, $client] = $this->createSale($user, 'Cliente saldado', 'POLLO SALDADO', '2026-08-28 08:00:00');
        $settlement = Cobranza::factory()->abono()->create([
            'numero_cobranza' => 'COB-SALDADA-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 170,
            'fecha_pago' => '2026-08-29 09:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.customer-account.ticket', [
            'cliente' => $client,
            'hasta' => '2026-08-30',
        ]));

        $response
            ->assertOk()
            ->assertViewHas('status', 'SALDADA')
            ->assertViewHas('cycleReset', true)
            ->assertViewHas('summary', [
                'sales_count' => 0,
                'total_sales' => 0,
                'collections_count' => 0,
                'total_collections' => 0,
                'remaining' => 0,
                'credit' => 0,
            ])
            ->assertSee('Cuenta saldada. El próximo ciclo comenzará con una nueva venta.')
            ->assertDontSee($settledSale->numero_venta)
            ->assertDontSee($settlement->numero_cobranza);
    }

    public function test_customer_account_detail_rejects_an_invalid_cutoff(): void
    {
        $user = $this->userWithPermission('REPORTES_VER');
        $client = Cliente::factory()->create();

        $this->actingAs($user)
            ->get(route('reportes.customer-account', [
                'cliente' => $client,
                'hasta' => '30/08/2026',
            ]))
            ->assertSessionHasErrors([
                'hasta' => 'La fecha de corte no es válida.',
            ]);
    }

    public function test_report_rejects_an_inverted_date_range(): void
    {
        $user = $this->userWithPermission('REPORTES_VER');

        $this->actingAs($user)
            ->get(route('reportes.show', [
                'report' => 'ventas',
                'desde' => '2026-08-20',
                'hasta' => '2026-08-01',
            ]))
            ->assertSessionHasErrors([
                'hasta' => 'La fecha final debe ser igual o posterior a la inicial.',
            ]);
    }

    public function test_csv_export_is_excel_compatible_and_neutralizes_formula_values(): void
    {
        $user = $this->userWithPermission('REPORTES_VER');
        $this->createSale($user, '=SUM(1+1)', 'POLLO CSV');

        $response = $this->actingAs($user)->get(route('reportes.csv', [
            'report' => 'ventas',
            'desde' => today()->toDateString(),
            'hasta' => today()->toDateString(),
        ]));

        $response
            ->assertOk()
            ->assertDownload()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Fecha;Venta;Cliente;Producto', $content);
        $this->assertStringContainsString("'=SUM(1+1)", $content);
    }

    public function test_printable_report_contains_period_and_pdf_action(): void
    {
        $user = $this->userWithPermission('REPORTES_VER');

        $this->actingAs($user)
            ->get(route('reportes.print', [
                'report' => 'caja',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Imprimir / Guardar como PDF')
            ->assertSee(today()->format('d/m/Y'));
    }

    public function test_cash_report_includes_non_cash_expenses_and_general_result(): void
    {
        $user = $this->userWithPermission('REPORTES_VER');
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => today(),
            'monto_apertura' => 100.00,
        ]);
        $yape = MedioPago::factory()->create(['nombre' => 'Yape reporte']);
        Cobranza::factory()->create([
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $yape->id,
            'monto_total' => 200.00,
        ]);
        PagoProveedor::factory()->create([
            'pagado_por' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'medio_pago_id' => $yape->id,
            'monto' => 50.00,
            'pagado_at' => $cashSession->apertura_at,
        ]);
        $cashSession->update([
            'cierre_at' => now(),
            'cerrada_por' => $user->id,
            'monto_contado_efectivo' => 100.00,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.show', [
            'report' => 'caja',
            'desde' => today()->toDateString(),
            'hasta' => today()->toDateString(),
        ]));

        $response
            ->assertOk()
            ->assertSee('Otros egresos')
            ->assertSee('Neto otros medios')
            ->assertSee('Resultado general')
            ->assertSee('S/ 250,00')
            ->assertViewHas('summary', [
                ['label' => 'Jornadas', 'value' => 1, 'format' => 'integer'],
                ['label' => 'Ingresos totales', 'value' => 200.0, 'format' => 'money'],
                ['label' => 'Egresos totales', 'value' => 50.0, 'format' => 'money'],
                ['label' => 'Resultado general', 'value' => 250.0, 'format' => 'money'],
            ]);
    }

    /**
     * @return array{0: Venta, 1: Cliente, 2: Producto}
     */
    private function createSale(Usuario $user, string $clientName, string $productName, mixed $soldAt = null, ?Cliente $client = null): array
    {
        $client ??= Cliente::factory()->create(['nombres_razon_social' => $clientName]);
        $product = Producto::factory()->create(['nombre' => $productName]);
        $saleDate = Carbon::parse($soldAt ?? now());
        $priceDay = PrecioDia::factory()->create([
            'producto_id' => $product->id,
            'fecha' => $saleDate->toDateString(),
        ]);
        $priceVersion = PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => 8.5,
            'vigente_desde' => $saleDate,
            'registrado_por' => $user->id,
        ]);
        $sale = Venta::factory()->create([
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'fecha_venta' => $saleDate,
        ]);
        $detail = VentaDetalle::factory()->create([
            'venta_id' => $sale->id,
            'precio_version_id' => $priceVersion->id,
            'precio_aplicado_kg' => 8.5,
        ]);
        PesajeVenta::factory()->directo()->create([
            'venta_detalle_id' => $detail->id,
            'cantidad_pollos' => 10,
            'peso_bruto_kg' => 20,
        ]);

        return [$sale, $client, $product];
    }

    private function userWithPermission(string $code): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => $code]);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
