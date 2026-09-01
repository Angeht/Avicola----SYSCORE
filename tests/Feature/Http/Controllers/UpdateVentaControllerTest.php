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

class UpdateVentaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cashier_can_edit_but_cannot_delete_a_sale(): void
    {
        $cashier = $this->userWithRoleAndPermissions('CAJA', ['VENTAS_EDITAR']);
        [, , $sale] = $this->saleWithStock();

        $this->actingAs($cashier)
            ->get(route('ventas.edit', $sale))
            ->assertOk()
            ->assertSee('Editar venta');
        $this->actingAs($cashier)
            ->get(route('ventas.anulacion.create', $sale))
            ->assertForbidden();
        $this->actingAs($cashier)
            ->post(route('ventas.anulacion.store', $sale), [
                'motivo_anulacion' => 'Intento de eliminación desde caja.',
            ])
            ->assertForbidden();
    }

    public function test_valid_edit_replaces_details_recalculates_stock_and_records_audit_data(): void
    {
        $this->travelTo('2026-08-30 09:30:00');
        $editor = $this->userWithRoleAndPermissions('CAJA', ['VENTAS_EDITAR']);
        $originalSeller = Usuario::factory()->create();
        [$product, $priceVersion, $sale] = $this->saleWithStock(originalSeller: $originalSeller);
        $oldDetailId = $sale->detalles()->value('id');
        $oldWeighingId = DB::table('pesajes_venta')->where('venta_detalle_id', $oldDetailId)->value('id');

        $response = $this->actingAs($editor)->put(route('ventas.update', $sale), [
            ...$this->validPayload($priceVersion, 15, '30.000'),
            'observacion' => '  peso   corregido  ',
            'motivo_edicion' => '  Corrección   del pesaje de salida  ',
        ]);

        $response
            ->assertRedirect(route('ventas.show', $sale))
            ->assertSessionHas('status', "Venta {$sale->numero_venta} actualizada correctamente.");
        $this->assertDatabaseHas('ventas', [
            'id' => $sale->id,
            'numero_venta' => $sale->numero_venta,
            'usuario_id' => $originalSeller->id,
            'observacion' => 'peso corregido',
            'editada_por' => $editor->id,
            'editada_at' => '2026-08-30 09:30:00',
            'motivo_edicion' => 'Corrección del pesaje de salida',
        ]);
        $this->assertDatabaseMissing('venta_detalles', ['id' => $oldDetailId]);
        $this->assertDatabaseMissing('pesajes_venta', ['id' => $oldWeighingId]);
        $this->assertDatabaseCount('venta_detalles', 1);
        $this->assertDatabaseCount('pesajes_venta', 1);
        $this->assertDatabaseHas('vw_totales_venta', [
            'venta_id' => $sale->id,
            'cantidad_pollos' => 15,
            'peso_neto_kg' => 30,
            'total_venta' => 300,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 85,
            'kg_disponibles' => 170,
        ]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $editor->id,
            'tabla_afectada' => 'ventas',
            'registro_id' => $sale->id,
            'accion' => 'UPDATE',
        ]);
    }

    public function test_edit_can_reuse_original_stock_but_cannot_exceed_it(): void
    {
        $editor = $this->userWithRoleAndPermissions('CAJA', ['VENTAS_EDITAR']);
        [$product, $priceVersion, $sale] = $this->saleWithStock();

        $this->actingAs($editor)
            ->put(route('ventas.update', $sale), [
                ...$this->validPayload($priceVersion, 100, '200.000'),
                'motivo_edicion' => 'Corrección usando el saldo completo.',
            ])
            ->assertRedirect(route('ventas.show', $sale));
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 0,
            'kg_disponibles' => 0,
        ]);

        $response = $this->actingAs($editor)
            ->from(route('ventas.edit', $sale))
            ->put(route('ventas.update', $sale), [
                ...$this->validPayload($priceVersion, 101, '201.000'),
                'motivo_edicion' => 'Intento de superar el stock disponible.',
            ]);

        $response->assertSessionHasErrors([
            'detalles.0.pesajes' => 'La venta supera la mercadería disponible para este producto.',
        ]);
        $this->assertDatabaseHas('vw_totales_venta', [
            'venta_id' => $sale->id,
            'cantidad_pollos' => 100,
            'peso_neto_kg' => 200,
        ]);
    }

    public function test_edit_cannot_reduce_total_below_active_collections(): void
    {
        $editor = $this->userWithRoleAndPermissions('CAJA', ['VENTAS_EDITAR']);
        $client = Cliente::factory()->create();
        [, $priceVersion, $sale] = $this->saleWithStock(client: $client);
        $collectionId = DB::table('cobranzas')->insertGetId([
            'numero_cobranza' => 'COB-20260830-000099',
            'cliente_id' => $client->id,
            'usuario_id' => $editor->id,
            'sesion_caja_id' => null,
            'medio_pago_id' => MedioPago::factory()->create()->id,
            'tipo' => 'PAGO_VENTA',
            'monto_total' => 150,
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
            'monto_aplicado' => 150,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($editor)
            ->from(route('ventas.edit', $sale))
            ->put(route('ventas.update', $sale), [
                ...$this->validPayload($priceVersion, 5, '10.000'),
                'motivo_edicion' => 'Corrección que reduce el total vendido.',
            ]);

        $response->assertSessionHasErrors([
            'detalles' => 'El nuevo total no puede ser menor que el monto ya cobrado de esta venta.',
        ]);
        $this->assertDatabaseHas('vw_totales_venta', [
            'venta_id' => $sale->id,
            'total_venta' => 200,
        ]);
    }

    public function test_deleted_sale_cannot_be_edited(): void
    {
        $editor = $this->userWithRoleAndPermissions('CAJA', ['VENTAS_EDITAR']);
        [, $priceVersion, $sale] = $this->saleWithStock();
        $administrator = $this->userWithRoleAndPermissions('ADMINISTRADOR OPERATIVO', ['VENTAS_ANULAR']);
        $sale->update([
            'anulada_por' => $administrator->id,
            'anulada_at' => now(),
            'motivo_anulacion' => 'Venta eliminada antes de la edición.',
        ]);

        $this->actingAs($editor)
            ->get(route('ventas.edit', $sale))
            ->assertStatus(409);
        $this->actingAs($editor)
            ->put(route('ventas.update', $sale), [
                ...$this->validPayload($priceVersion, 5, '10.000'),
                'motivo_edicion' => 'Intento de editar una venta eliminada.',
            ])
            ->assertSessionHasErrors([
                'motivo_edicion' => 'Una venta eliminada no puede editarse.',
            ]);
    }

    /** @return array{Producto, PrecioDiaVersion, Venta} */
    private function saleWithStock(?Usuario $originalSeller = null, ?Cliente $client = null): array
    {
        $product = Producto::factory()->create(['nombre' => 'POLLO VIVO EDITABLE']);
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
            'usuario_id' => ($originalSeller ?? Usuario::factory()->create())->id,
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

        return [$product, $priceVersion, $sale];
    }

    /** @return array<string, mixed> */
    private function validPayload(PrecioDiaVersion $priceVersion, int $birds, string $kilograms): array
    {
        return [
            'cliente_id' => null,
            'observacion' => null,
            'detalles' => [[
                'precio_version_id' => $priceVersion->id,
                'precio_aplicado_kg' => '10.0000',
                'motivo_ajuste_precio' => null,
                'pesajes' => [[
                    'tipo_jaba_id' => null,
                    'cantidad_jabas' => 0,
                    'cantidad_pollos' => $birds,
                    'peso_bruto_kg' => $kilograms,
                    'tara_unitaria_aplicada_kg' => '0.000',
                    'observacion' => null,
                ]],
            ]],
        ];
    }

    /** @param list<string> $permissionCodes */
    private function userWithRoleAndPermissions(string $roleName, array $permissionCodes): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create(['nombre' => $roleName]);
        $permissions = collect($permissionCodes)->map(fn (string $code): Permiso => Permiso::query()->firstOrCreate(
            ['codigo' => $code],
            ['nombre' => $code],
        ));
        $role->permisos()->attach($permissions->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }
}
