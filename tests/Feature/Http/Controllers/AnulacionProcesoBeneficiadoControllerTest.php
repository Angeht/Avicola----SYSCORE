<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteMercaderia;
use App\Models\CargaProveedor;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AnulacionProcesoBeneficiadoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $process = $this->createProcess();

        $this->get(route('beneficiados.anulacion.create', $process))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_merchandise_permission_is_forbidden(): void
    {
        $process = $this->createProcess();

        $this->actingAs(Usuario::factory()->create())
            ->get(route('beneficiados.anulacion.create', $process))
            ->assertForbidden();
    }

    public function test_valid_cancellation_restores_live_stock_and_removes_benefited_stock(): void
    {
        $this->travelTo('2026-08-30 18:30:45');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $process = $this->createProcess();
        $process->load('cargaProveedor.producto', 'productoDestino');
        $sourceProduct = $process->cargaProveedor->producto;
        $destinationProduct = $process->productoDestino;

        $this->actingAs($user)
            ->get(route('beneficiados.anulacion.create', $process))
            ->assertOk()
            ->assertSee($process->numero_proceso)
            ->assertSee('Confirmar anulación');

        $response = $this->actingAs($user)->post(route('beneficiados.anulacion.store', $process), [
            'motivo_anulacion' => '  peso   de salida registrado incorrectamente  ',
            'anulado_por' => $otherUser->id,
            'anulado_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('beneficiados.show', $process))
            ->assertSessionHas('status', "Beneficiado {$process->numero_proceso} anulado correctamente.");
        $this->assertDatabaseHas('procesos_beneficiado', [
            'id' => $process->id,
            'anulado_por' => $user->id,
            'anulado_at' => '2026-08-30 18:30:45',
            'motivo_anulacion' => 'peso de salida registrado incorrectamente',
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $sourceProduct->id,
            'pollos_disponibles' => 100,
            'kg_disponibles' => 250.000,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $destinationProduct->id,
            'pollos_disponibles' => 0,
            'kg_disponibles' => 0.000,
        ]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'procesos_beneficiado',
            'registro_id' => $process->id,
            'accion' => 'ANULAR',
        ]);
    }

    public function test_process_cannot_be_cancelled_after_benefited_stock_was_used(): void
    {
        $user = $this->userWithPermission();
        $process = $this->createProcess();
        $outgoingType = TipoAjusteMercaderia::factory()->salida()->create();
        AjusteMercaderia::factory()->create([
            'producto_id' => $process->producto_destino_id,
            'tipo_ajuste_id' => $outgoingType->id,
            'cantidad_pollos' => 0,
            'peso_kg' => 1.000,
        ]);

        $this->actingAs($user)
            ->get(route('beneficiados.anulacion.create', $process))
            ->assertStatus(409);

        $this->actingAs($user)
            ->from(route('beneficiados.show', $process))
            ->post(route('beneficiados.anulacion.store', $process), [
                'motivo_anulacion' => 'Se detectó un error en el proceso.',
            ])
            ->assertSessionHasErrors([
                'motivo_anulacion' => 'No puedes anular el proceso porque parte del producto beneficiado ya fue utilizado.',
            ]);
        $this->assertDatabaseHas('procesos_beneficiado', [
            'id' => $process->id,
            'anulado_at' => null,
        ]);
    }

    public function test_cancelled_process_cannot_be_cancelled_again(): void
    {
        $user = $this->userWithPermission();
        $process = $this->createProcess();
        $process->update([
            'anulado_por' => $user->id,
            'anulado_at' => now(),
            'motivo_anulacion' => 'Proceso anulado antes de repetir la solicitud.',
        ]);

        $this->actingAs($user)
            ->from(route('beneficiados.show', $process))
            ->post(route('beneficiados.anulacion.store', $process), [
                'motivo_anulacion' => 'Segundo intento de anulación del proceso.',
            ])
            ->assertSessionHasErrors([
                'motivo_anulacion' => 'Este proceso de beneficiado ya fue anulado.',
            ]);
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

    private function createProcess(): ProcesoBeneficiado
    {
        $sourceProduct = Producto::factory()->create(['nombre' => 'POLLO VIVO']);
        $destinationProduct = Producto::factory()->soloPeso()->create(['nombre' => 'POLLO BENEFICIADO']);
        $load = CargaProveedor::factory()->create([
            'producto_id' => $sourceProduct->id,
            'fecha_carga' => today(),
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => 100,
            'peso_bruto_kg' => 250.000,
        ]);

        return ProcesoBeneficiado::factory()->create([
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
            'cantidad_pollos' => 40,
            'peso_origen_kg' => 100.000,
            'peso_resultante_kg' => 78.000,
        ]);
    }
}
