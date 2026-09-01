<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProcesoBeneficiadoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('beneficiados.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_merchandise_permission_is_forbidden(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->get(route('beneficiados.index'))
            ->assertForbidden();
    }

    public function test_create_form_lists_live_loads_with_stock_and_weight_only_destinations(): void
    {
        $user = $this->userWithPermission();
        $liveProduct = Producto::factory()->create(['nombre' => 'POLLO VIVO ORIGEN']);
        $destinationProduct = Producto::factory()->soloPeso()->create(['nombre' => 'POLLO BENEFICIADO DESTINO']);
        $availableLoad = $this->createLoad($liveProduct, 100, '250.000');
        $emptyLoad = CargaProveedor::factory()->create([
            'numero_carga' => 'CAR-SIN-PESAJE',
            'producto_id' => $liveProduct->id,
        ]);
        $invalidSourceProduct = Producto::factory()->soloPeso()->create(['nombre' => 'ORIGEN SOLO PESO']);
        $invalidLoad = $this->createLoad($invalidSourceProduct, 50, '80.000');

        $this->actingAs($user)
            ->get(route('beneficiados.create'))
            ->assertOk()
            ->assertSee($availableLoad->numero_carga)
            ->assertSee('100 aves / 250,000 kg')
            ->assertSee('POLLO BENEFICIADO DESTINO')
            ->assertDontSee($emptyLoad->numero_carga)
            ->assertDontSee($invalidLoad->numero_carga);

        $this->assertTrue($destinationProduct->seVendeSoloPorPeso());
    }

    public function test_valid_process_moves_live_stock_to_benefited_stock_and_calculates_yield(): void
    {
        $this->travelTo('2026-08-30 10:15:20');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $sourceProduct = Producto::factory()->create(['nombre' => 'POLLO VIVO']);
        $destinationProduct = Producto::factory()->soloPeso()->create(['nombre' => 'POLLO BENEFICIADO']);
        $load = $this->createLoad($sourceProduct, 100, '250.000');

        $response = $this->actingAs($user)->post(route('beneficiados.store'), [
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'cantidad_pollos' => 40,
            'peso_origen_kg' => '100,000',
            'peso_resultante_kg' => '78,000',
            'observacion' => '  turno   de la mañana  ',
            'numero_proceso' => 'MANIPULADO',
            'procesado_at' => '2000-01-01 00:00:00',
            'procesado_por' => $otherUser->id,
        ]);

        $process = ProcesoBeneficiado::query()->firstOrFail();
        $expectedNumber = sprintf('BEN-20260830-%06d', $process->id);

        $response
            ->assertRedirect(route('beneficiados.show', $process))
            ->assertSessionHas('status', "Beneficiado $expectedNumber registrado correctamente.");
        $this->assertDatabaseHas('procesos_beneficiado', [
            'id' => $process->id,
            'numero_proceso' => $expectedNumber,
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'cantidad_pollos' => 40,
            'peso_origen_kg' => 100.000,
            'peso_resultante_kg' => 78.000,
            'procesado_at' => '2026-08-30 10:15:20',
            'procesado_por' => $user->id,
            'observacion' => 'turno de la mañana',
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $sourceProduct->id,
            'pollos_disponibles' => 60,
            'kg_disponibles' => 150.000,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $destinationProduct->id,
            'pollos_disponibles' => 0,
            'kg_disponibles' => 78.000,
        ]);
        $this->assertSame(22.0, $process->mermaKg());
        $this->assertSame(78.0, $process->rendimientoPorcentaje());
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'procesos_beneficiado',
            'registro_id' => $process->id,
            'accion' => 'INSERT',
        ]);
    }

    public function test_index_displays_registered_process_and_yield(): void
    {
        $user = $this->userWithPermission();
        $sourceProduct = Producto::factory()->create(['nombre' => 'POLLO VIVO ÍNDICE']);
        $destinationProduct = Producto::factory()->soloPeso()->create(['nombre' => 'POLLO BENEFICIADO ÍNDICE']);
        $load = $this->createLoad($sourceProduct, 100, '250.000');
        $process = ProcesoBeneficiado::factory()->create([
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'peso_origen_kg' => 100,
            'peso_resultante_kg' => 78,
        ]);

        $this->actingAs($user)
            ->get(route('beneficiados.index'))
            ->assertOk()
            ->assertSee($process->numero_proceso)
            ->assertSee($load->numero_carga)
            ->assertSee('POLLO BENEFICIADO ÍNDICE')
            ->assertSee('78,00%');
    }

    public function test_benefited_weight_cannot_exceed_live_weight(): void
    {
        $user = $this->userWithPermission();
        $sourceProduct = Producto::factory()->create();
        $destinationProduct = Producto::factory()->soloPeso()->create();
        $load = $this->createLoad($sourceProduct, 100, '250.000');

        $this->actingAs($user)->post(route('beneficiados.store'), [
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'cantidad_pollos' => 10,
            'peso_origen_kg' => '20.000',
            'peso_resultante_kg' => '20.001',
        ])->assertSessionHasErrors([
            'peso_resultante_kg' => 'El peso beneficiado no puede superar el peso vivo procesado.',
        ]);

        $this->assertDatabaseCount('procesos_beneficiado', 0);
    }

    public function test_process_cannot_exceed_remaining_load_or_live_stock(): void
    {
        $user = $this->userWithPermission();
        $sourceProduct = Producto::factory()->create();
        $destinationProduct = Producto::factory()->soloPeso()->create();
        $load = $this->createLoad($sourceProduct, 100, '250.000');
        ProcesoBeneficiado::factory()->create([
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'cantidad_pollos' => 80,
            'peso_origen_kg' => 200,
            'peso_resultante_kg' => 150,
        ]);

        $response = $this->actingAs($user)->post(route('beneficiados.store'), [
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'cantidad_pollos' => 21,
            'peso_origen_kg' => '50.001',
            'peso_resultante_kg' => '40.000',
        ]);

        $response->assertSessionHasErrors([
            'cantidad_pollos' => 'La cantidad supera las aves disponibles de la carga y del stock vivo.',
            'peso_origen_kg' => 'El peso supera los kilogramos disponibles de la carga y del stock vivo.',
        ]);
        $this->assertDatabaseCount('procesos_beneficiado', 1);
    }

    public function test_show_escapes_the_observation_and_displays_traceability(): void
    {
        $user = $this->userWithPermission();
        $sourceProduct = Producto::factory()->create(['nombre' => 'POLLO VIVO TRAZABLE']);
        $destinationProduct = Producto::factory()->soloPeso()->create(['nombre' => 'POLLO BENEFICIADO TRAZABLE']);
        $load = $this->createLoad($sourceProduct, 100, '250.000');
        $dangerousObservation = '<script>alert("beneficiado")</script> turno especial';
        $process = ProcesoBeneficiado::factory()->create([
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'observacion' => $dangerousObservation,
        ]);

        $this->actingAs($user)
            ->get(route('beneficiados.show', $process))
            ->assertOk()
            ->assertSee($load->numero_carga)
            ->assertSee('POLLO VIVO TRAZABLE')
            ->assertSee('POLLO BENEFICIADO TRAZABLE')
            ->assertSee($dangerousObservation)
            ->assertDontSee($dangerousObservation, false);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::query()->firstOrCreate(
            ['codigo' => 'MERCADERIA_AJUSTAR'],
            ['nombre' => 'Registrar ajustes de mercadería'],
        );

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }

    private function createLoad(Producto $product, int $birds, string $kilograms): CargaProveedor
    {
        $load = CargaProveedor::factory()->create([
            'producto_id' => $product->id,
            'fecha_carga' => today(),
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => $birds,
            'peso_bruto_kg' => $kilograms,
        ]);

        return $load;
    }
}
