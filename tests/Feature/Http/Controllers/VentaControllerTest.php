<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteMercaderia;
use App\Models\CargaProveedor;
use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\PrecioDia;
use App\Models\PrecioDiaVersion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\SesionCaja;
use App\Models\TipoAjusteMercaderia;
use App\Models\TipoJaba;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VentaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('ventas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_sales_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('ventas.index'))
            ->assertForbidden();
    }

    public function test_create_lists_every_product_with_current_price_and_exposes_its_sale_mode(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [$sellableProduct] = $this->sellableProduct('POLLO DISPONIBLE');
        $productWithoutPrice = Producto::factory()->create(['nombre' => 'POLLO SIN PRECIO']);
        $productWithoutStock = Producto::factory()->soloPeso()->create(['nombre' => 'POLLO BENEFICIADO SIN STOCK']);
        $priceDay = PrecioDia::factory()->create(['producto_id' => $productWithoutStock->id]);
        PrecioDiaVersion::factory()->create(['precio_dia_id' => $priceDay->id]);
        $activeClient = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE ACTIVO']);
        Cliente::factory()->inactivo()->create(['nombres_razon_social' => 'CLIENTE INACTIVO']);

        $response = $this->actingAs($user)->get(route('ventas.create'));

        $response
            ->assertOk()
            ->assertSee($sellableProduct->nombre)
            ->assertSee($productWithoutStock->nombre)
            ->assertSee('data-sale-mode="SOLO_PESO"', false)
            ->assertSee($activeClient->nombres_razon_social)
            ->assertDontSee($productWithoutPrice->nombre)
            ->assertDontSee('CLIENTE INACTIVO');
    }

    public function test_valid_sale_creates_details_weighings_totals_and_server_audit_data(): void
    {
        $this->travelTo('2026-08-27 11:20:30');
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        $otherUser = Usuario::factory()->create();
        $client = Cliente::factory()->create();
        [$product, $priceVersion] = $this->sellableProduct('POLLO BENEFICIADO', 100, 200, 8.5);
        $cashSession = SesionCaja::factory()->create([
            'usuario_id' => $user->id,
            'fecha_operacion' => '2026-08-27',
            'apertura_at' => '2026-08-27 08:00:00',
        ]);

        $response = $this->actingAs($user)->post(route('ventas.store'), [
            'cliente_id' => $client->id,
            'observacion' => '  pedido   de mostrador  ',
            'numero_venta' => 'MANIPULADA',
            'usuario_id' => $otherUser->id,
            'fecha_venta' => '2000-01-01 00:00:00',
            'detalles' => [[
                'precio_version_id' => $priceVersion->id,
                'precio_aplicado_kg' => '8,5000',
                'motivo_ajuste_precio' => null,
                'pesajes' => [
                    [
                        'tipo_jaba_id' => null,
                        'cantidad_jabas' => 0,
                        'cantidad_pollos' => 10,
                        'peso_bruto_kg' => '20,000',
                        'tara_unitaria_aplicada_kg' => '99.000',
                        'observacion' => '  salida   directa ',
                    ],
                    [
                        'tipo_jaba_id' => null,
                        'cantidad_jabas' => 0,
                        'cantidad_pollos' => 5,
                        'peso_bruto_kg' => '10.000',
                        'tara_unitaria_aplicada_kg' => '0',
                        'observacion' => null,
                    ],
                ],
            ]],
        ]);

        $sale = Venta::query()->firstOrFail();
        $saleDetail = VentaDetalle::query()->firstOrFail();
        $expectedNumber = sprintf('VEN-20260827-%06d', $sale->id);
        $response
            ->assertRedirect(route('ventas.show', $sale))
            ->assertSessionHas('status', "Venta $expectedNumber registrada correctamente.");
        $this->assertDatabaseHas('ventas', [
            'id' => $sale->id,
            'numero_venta' => $expectedNumber,
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'sesion_caja_id' => $cashSession->id,
            'fecha_venta' => '2026-08-27 11:20:30',
            'observacion' => 'pedido de mostrador',
            'anulada_at' => null,
        ]);
        $this->assertDatabaseHas('venta_detalles', [
            'id' => $saleDetail->id,
            'precio_version_id' => $priceVersion->id,
            'precio_aplicado_kg' => 8.5,
            'motivo_ajuste_precio' => null,
        ]);
        $this->assertDatabaseHas('pesajes_venta', [
            'venta_detalle_id' => $saleDetail->id,
            'tipo_pesaje' => 'DIRECTO',
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => 10,
            'peso_bruto_kg' => 20,
            'tara_unitaria_aplicada_kg' => 0,
            'observacion' => 'salida directa',
        ]);
        $this->assertDatabaseCount('pesajes_venta', 2);
        $this->assertDatabaseHas('vw_totales_venta', [
            'venta_id' => $sale->id,
            'cantidad_pollos' => 15,
            'peso_neto_kg' => 30,
            'total_venta' => 255,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 85,
            'kg_disponibles' => 170,
        ]);
        $this->assertDatabaseMissing('ventas', ['usuario_id' => $otherUser->id]);
    }

    public function test_sale_cannot_exceed_available_stock(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [, $priceVersion] = $this->sellableProduct('POLLO LIMITADO', 10, 20, 10);
        $payload = $this->validPayload($priceVersion);
        $payload['detalles'][0]['pesajes'][0]['cantidad_pollos'] = 11;

        $response = $this->actingAs($user)
            ->from(route('ventas.create'))
            ->post(route('ventas.store'), $payload);

        $response->assertSessionHasErrors([
            'detalles.0.pesajes' => 'La venta supera la mercadería disponible para este producto.',
        ]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_weight_only_product_is_sold_with_kilograms_and_without_birds_or_crates(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [$product, $priceVersion] = $this->weightOnlyProduct('POLLO BENEFICIADO', 50, 6);
        $payload = $this->validPayload($priceVersion);
        $payload['detalles'][0]['precio_aplicado_kg'] = '6.0000';
        $payload['detalles'][0]['pesajes'][0] = [
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => 0,
            'peso_bruto_kg' => '12.500',
            'tara_unitaria_aplicada_kg' => '0.000',
            'observacion' => 'Entrega beneficiada',
        ];

        $response = $this->actingAs($user)->post(route('ventas.store'), $payload);

        $sale = Venta::query()->firstOrFail();
        $response->assertRedirect(route('ventas.show', $sale));
        $this->assertDatabaseHas('pesajes_venta', [
            'tipo_pesaje' => 'DIRECTO',
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => 0,
            'peso_bruto_kg' => 12.5,
            'tara_unitaria_aplicada_kg' => 0,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 0,
            'kg_disponibles' => 37.5,
        ]);
        $this->assertDatabaseHas('vw_totales_venta', [
            'venta_id' => $sale->id,
            'cantidad_pollos' => 0,
            'peso_neto_kg' => 12.5,
            'total_venta' => 75,
        ]);
    }

    public function test_show_displays_calculated_tare_and_net_weight_for_each_weighing(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [, $priceVersion] = $this->sellableProduct('POLLO CON JABA');
        $sale = Venta::factory()->create();
        $detail = VentaDetalle::factory()->create([
            'venta_id' => $sale->id,
            'precio_version_id' => $priceVersion->id,
            'precio_aplicado_kg' => 10,
        ]);
        $crateType = TipoJaba::factory()->create();
        $detail->pesajes()->create([
            'tipo_pesaje' => 'CON_JABA',
            'tipo_jaba_id' => $crateType->id,
            'cantidad_jabas' => 2,
            'cantidad_pollos' => 10,
            'peso_bruto_kg' => 101,
            'tara_unitaria_aplicada_kg' => 1,
            'observacion' => null,
        ]);

        $this->actingAs($user)
            ->get(route('ventas.show', $sale))
            ->assertOk()
            ->assertSee('S/ 10,00')
            ->assertDontSee('S/ 10,0000')
            ->assertSee('− 2,000 kg')
            ->assertSee('99,000 kg');
    }

    public function test_create_form_displays_unit_prices_without_redundant_zeroes(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        $this->sellableProduct('POLLO PRECIO LEGIBLE', 100, 200, 7.25);

        $response = $this->actingAs($user)->get(route('ventas.create'));

        $response
            ->assertOk()
            ->assertSee('S/ 7,25/kg')
            ->assertDontSee('S/ 7,2500/kg');
    }

    public function test_create_form_lists_a_product_with_current_price_even_without_stock(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        $product = Producto::factory()->create(['nombre' => 'POLLO SIN EXISTENCIA']);
        $priceDay = PrecioDia::factory()->create([
            'producto_id' => $product->id,
            'fecha' => today(),
        ]);
        PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => 8.75,
            'vigente_desde' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('ventas.create'));

        $response
            ->assertSee('POLLO SIN EXISTENCIA')
            ->assertSee('data-birds="0"', false)
            ->assertSee('data-kilograms="0.000"', false);
    }

    public function test_weight_only_product_rejects_birds_and_crates(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [, $priceVersion] = $this->weightOnlyProduct('PRODUCTO SOLO PESO', 50, 6);
        $payload = $this->validPayload($priceVersion);
        $payload['detalles'][0]['precio_aplicado_kg'] = '6.0000';
        $payload['detalles'][0]['pesajes'][0]['cantidad_jabas'] = 2;
        $payload['detalles'][0]['pesajes'][0]['cantidad_pollos'] = 1;

        $response = $this->actingAs($user)->post(route('ventas.store'), $payload);

        $response->assertSessionHasErrors([
            'detalles.0.pesajes.0.cantidad_jabas' => 'Los productos de solo peso no utilizan jabas ni tara.',
            'detalles.0.pesajes.0.cantidad_pollos' => 'Los productos de solo peso se registran únicamente en kilogramos.',
        ]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_live_product_requires_at_least_one_bird_per_weighing(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [, $priceVersion] = $this->sellableProduct('POLLO VIVO CONTROLADO');
        $payload = $this->validPayload($priceVersion);
        $payload['detalles'][0]['pesajes'][0]['cantidad_pollos'] = 0;

        $response = $this->actingAs($user)->post(route('ventas.store'), $payload);

        $response->assertSessionHasErrors([
            'detalles.0.pesajes.0.cantidad_pollos' => 'Cada pesaje de producto vivo debe incluir al menos un pollo.',
        ]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_user_without_price_override_permission_cannot_change_current_price(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [, $priceVersion] = $this->sellableProduct('POLLO PRECIO FIJO', 100, 200, 10);
        $payload = $this->validPayload($priceVersion);
        $payload['detalles'][0]['precio_aplicado_kg'] = '9.5000';
        $payload['detalles'][0]['motivo_ajuste_precio'] = 'Descuento comercial autorizado.';

        $response = $this->actingAs($user)
            ->from(route('ventas.create'))
            ->post(route('ventas.store'), $payload);

        $response->assertSessionHasErrors([
            'detalles.0.precio_aplicado_kg' => 'No tienes permiso para modificar el precio vigente.',
        ]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_authorized_price_override_requires_and_preserves_reason(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR', 'PRECIO_VENTA_EDITAR']);
        [, $priceVersion] = $this->sellableProduct('POLLO NEGOCIADO', 100, 200, 10);
        $payload = $this->validPayload($priceVersion);
        $payload['detalles'][0]['precio_aplicado_kg'] = '9.5000';
        $payload['detalles'][0]['motivo_ajuste_precio'] = '  descuento   por volumen  ';

        $this->actingAs($user)
            ->post(route('ventas.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('venta_detalles', [
            'precio_version_id' => $priceVersion->id,
            'precio_aplicado_kg' => 9.5,
            'motivo_ajuste_precio' => 'descuento por volumen',
        ]);
    }

    public function test_duplicate_product_and_stale_price_are_rejected(): void
    {
        $this->travelTo('2026-08-27 12:00:00');
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        [, $staleVersion] = $this->sellableProduct('POLLO CON CAMBIO', 100, 200, 10, '2026-08-27 10:00:00');
        PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $staleVersion->precio_dia_id,
            'precio_kg' => 11,
            'vigente_desde' => '2026-08-27 11:00:00',
        ]);
        $detail = $this->validPayload($staleVersion)['detalles'][0];

        $response = $this->actingAs($user)
            ->from(route('ventas.create'))
            ->post(route('ventas.store'), [
                'cliente_id' => null,
                'observacion' => null,
                'detalles' => [$detail, $detail],
            ]);

        $response->assertSessionHasErrors([
            'detalles.1.precio_version_id' => 'Cada producto solo puede aparecer una vez en la venta.',
            'detalles.0.precio_version_id' => 'El precio seleccionado ya no está vigente para la fecha actual.',
        ]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_index_filters_sales_and_show_escapes_observations(): void
    {
        $user = $this->userWithPermissions(['VENTAS_REGISTRAR']);
        $matchingClient = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE EL SOL']);
        $otherClient = Cliente::factory()->create(['nombres_razon_social' => 'CLIENTE DEL NORTE']);
        $dangerousText = '<script>alert("venta")</script>';
        $matchingSale = Venta::factory()->create([
            'cliente_id' => $matchingClient->id,
            'numero_venta' => 'VEN-20260827-000901',
            'observacion' => $dangerousText,
        ]);
        $otherSale = Venta::factory()->create([
            'cliente_id' => $otherClient->id,
            'numero_venta' => 'VEN-20260827-000902',
        ]);
        $this->addSaleDetail($matchingSale);
        $this->addSaleDetail($otherSale);

        $this->actingAs($user)
            ->get(route('ventas.index', ['buscar' => 'el sol']))
            ->assertOk()
            ->assertSee($matchingSale->numero_venta)
            ->assertDontSee($otherSale->numero_venta);

        $this->actingAs($user)
            ->get(route('ventas.show', $matchingSale))
            ->assertOk()
            ->assertSee($dangerousText)
            ->assertDontSee($dangerousText, false);
    }

    /**
     * @return array{Producto, PrecioDiaVersion}
     */
    private function sellableProduct(string $name, int $birds = 100, float $kilograms = 200, float $price = 10, mixed $effectiveAt = null): array
    {
        $product = Producto::factory()->create(['nombre' => $name]);
        $load = CargaProveedor::factory()->create([
            'producto_id' => $product->id,
            'fecha_carga' => today(),
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => $birds,
            'peso_bruto_kg' => $kilograms,
        ]);
        $priceDay = PrecioDia::factory()->create([
            'producto_id' => $product->id,
            'fecha' => today(),
        ]);
        $priceVersion = PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => $price,
            'vigente_desde' => $effectiveAt ?? now(),
        ]);

        return [$product, $priceVersion];
    }

    /**
     * @return array{Producto, PrecioDiaVersion}
     */
    private function weightOnlyProduct(string $name, float $kilograms, float $price): array
    {
        $product = Producto::factory()->soloPeso()->create(['nombre' => $name]);
        AjusteMercaderia::factory()->create([
            'producto_id' => $product->id,
            'tipo_ajuste_id' => TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA'])->id,
            'cantidad_pollos' => 0,
            'peso_kg' => $kilograms,
        ]);
        $priceDay = PrecioDia::factory()->create([
            'producto_id' => $product->id,
            'fecha' => today(),
        ]);
        $priceVersion = PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => $price,
            'vigente_desde' => now(),
        ]);

        return [$product, $priceVersion];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(PrecioDiaVersion $priceVersion): array
    {
        return [
            'cliente_id' => null,
            'observacion' => null,
            'detalles' => [[
                'precio_version_id' => $priceVersion->id,
                'precio_aplicado_kg' => $priceVersion->precio_kg,
                'motivo_ajuste_precio' => null,
                'pesajes' => [[
                    'tipo_jaba_id' => null,
                    'cantidad_jabas' => 0,
                    'cantidad_pollos' => 10,
                    'peso_bruto_kg' => '20.000',
                    'tara_unitaria_aplicada_kg' => '0.000',
                    'observacion' => null,
                ]],
            ]],
        ];
    }

    private function addSaleDetail(Venta $sale): void
    {
        $priceDay = PrecioDia::factory()->create(['fecha' => $sale->fecha_venta->toDateString()]);
        $priceVersion = PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => 10,
            'vigente_desde' => $sale->fecha_venta,
        ]);
        $detail = VentaDetalle::factory()->create([
            'venta_id' => $sale->id,
            'precio_version_id' => $priceVersion->id,
            'precio_aplicado_kg' => 10,
        ]);
        $detail->pesajes()->create([
            'tipo_pesaje' => 'DIRECTO',
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => 10,
            'peso_bruto_kg' => 20,
            'tara_unitaria_aplicada_kg' => 0,
            'observacion' => null,
        ]);
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permissions = collect($permissionCodes)
            ->map(fn (string $code): Permiso => Permiso::factory()->create(['codigo' => $code]));

        $role->permisos()->attach($permissions->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }
}
