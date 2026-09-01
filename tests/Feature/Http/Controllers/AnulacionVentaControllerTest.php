<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\Cliente;
use App\Models\MedioPago;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\PrecioDia;
use App\Models\PrecioDiaVersion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnulacionVentaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        [, $sale] = $this->saleWithStock();

        $this->get(route('ventas.anulacion.create', $sale))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_cancellation_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();
        [, $sale] = $this->saleWithStock();

        $this->actingAs($user)
            ->get(route('ventas.anulacion.create', $sale))
            ->assertForbidden();
    }

    public function test_cashier_or_custom_role_cannot_delete_even_with_legacy_permission(): void
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create(['nombre' => 'CAJA']);
        $permission = Permiso::factory()->create(['codigo' => 'VENTAS_ANULAR']);
        $role->permisos()->attach($permission);
        $user->roles()->attach($role);
        [, $sale] = $this->saleWithStock();

        $this->actingAs($user)
            ->get(route('ventas.anulacion.create', $sale))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('ventas.anulacion.store', $sale), [
                'motivo_anulacion' => 'Intento de eliminación desde caja.',
            ])
            ->assertForbidden();
    }

    public function test_valid_cancellation_restores_stock_and_uses_server_audit_data(): void
    {
        $this->travelTo('2026-08-27 15:30:10');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        [$product, $sale] = $this->saleWithStock();
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 90,
            'kg_disponibles' => 180,
        ]);

        $response = $this->actingAs($user)->post(route('ventas.anulacion.store', $sale), [
            'motivo_anulacion' => '  cliente   canceló el pedido ',
            'anulada_por' => $otherUser->id,
            'anulada_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('ventas.show', $sale))
            ->assertSessionHas('status', "Venta {$sale->numero_venta} eliminada correctamente.");
        $this->assertDatabaseHas('ventas', [
            'id' => $sale->id,
            'anulada_por' => $user->id,
            'anulada_at' => '2026-08-27 15:30:10',
            'motivo_anulacion' => 'cliente canceló el pedido',
        ]);
        $this->assertDatabaseMissing('ventas', [
            'id' => $sale->id,
            'anulada_por' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 100,
            'kg_disponibles' => 200,
        ]);
        $this->assertDatabaseHas('vw_saldos_venta', [
            'venta_id' => $sale->id,
            'estado_pago' => 'ANULADA',
        ]);
    }

    public function test_cancelled_sale_cannot_be_cancelled_again(): void
    {
        $user = $this->userWithPermission();
        [, $sale] = $this->saleWithStock();
        $sale->update([
            'anulada_por' => $user->id,
            'anulada_at' => now(),
            'motivo_anulacion' => 'Primera anulación registrada.',
        ]);

        $response = $this->actingAs($user)
            ->from(route('ventas.show', $sale))
            ->post(route('ventas.anulacion.store', $sale), [
                'motivo_anulacion' => 'Segundo intento de anulación.',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'Esta venta ya fue anulada.',
        ]);
    }

    public function test_sale_with_active_collection_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermission();
        $client = Cliente::factory()->create();
        [, $sale] = $this->saleWithStock($client);
        $paymentMethod = MedioPago::factory()->create();
        $collectionId = DB::table('cobranzas')->insertGetId([
            'numero_cobranza' => 'COB-20260827-000001',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'sesion_caja_id' => null,
            'medio_pago_id' => $paymentMethod->id,
            'tipo' => 'PAGO_VENTA',
            'monto_total' => 50,
            'fecha_pago' => now(),
            'observacion' => null,
            'anulada_por' => null,
            'anulada_at' => null,
            'motivo_anulacion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('aplicacion_cobranzas')->insert([
            'cobranza_id' => $collectionId,
            'venta_id' => $sale->id,
            'monto_aplicado' => 50,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('ventas.anulacion.create', $sale))
            ->assertStatus(409);

        $response = $this->actingAs($user)
            ->from(route('ventas.show', $sale))
            ->post(route('ventas.anulacion.store', $sale), [
                'motivo_anulacion' => 'El pedido debe corregirse completamente.',
            ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'Anula primero las cobranzas vigentes aplicadas a esta venta.',
        ]);
        $this->assertDatabaseHas('ventas', [
            'id' => $sale->id,
            'anulada_at' => null,
        ]);
    }

    /**
     * @return array{Producto, Venta}
     */
    private function saleWithStock(?Cliente $client = null): array
    {
        $product = Producto::factory()->create();
        $load = CargaProveedor::factory()->create([
            'producto_id' => $product->id,
            'fecha_carga' => today(),
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => 100,
            'peso_bruto_kg' => 200,
        ]);
        $priceDay = PrecioDia::factory()->create([
            'producto_id' => $product->id,
            'fecha' => today(),
        ]);
        $priceVersion = PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => 10,
            'vigente_desde' => now(),
        ]);
        $sale = Venta::factory()->create([
            'cliente_id' => $client?->id,
            'fecha_venta' => now(),
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

        return [$product, $sale];
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create(['nombre' => 'ADMINISTRADOR OPERATIVO']);
        $permission = Permiso::factory()->create(['codigo' => 'VENTAS_ANULAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
