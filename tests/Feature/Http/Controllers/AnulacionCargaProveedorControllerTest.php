<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AnulacionCargaProveedorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $load = CargaProveedor::factory()->create();

        $this->get(route('cargas-proveedor.anulacion.create', $load))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_cancellation_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();
        $load = CargaProveedor::factory()->create();

        $this->actingAs($user)
            ->get(route('cargas-proveedor.anulacion.create', $load))
            ->assertForbidden();
    }

    public function test_valid_cancellation_removes_stock_and_debt_and_uses_server_audit_data(): void
    {
        $this->travelTo('2026-08-29 16:45:10');
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $otherUser = Usuario::factory()->create();
        $product = Producto::factory()->create();
        $load = CargaProveedor::factory()->create([
            'producto_id' => $product->id,
            'fecha_carga' => today(),
            'costo_total' => 450.50,
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => 100,
            'peso_bruto_kg' => 200,
        ]);

        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 100,
            'kg_disponibles' => 200,
        ]);
        $this->actingAs($user)
            ->get(route('cargas-proveedor.anulacion.create', $load))
            ->assertOk()
            ->assertSee($load->numero_carga)
            ->assertSee($administrator->nombreCompleto())
            ->assertSee('PIN administrativo')
            ->assertSee('Confirmar anulación');

        $response = $this->actingAs($user)->post(route('cargas-proveedor.anulacion.store', $load), [
            'administrador_id' => $administrator->id,
            'pin_autorizacion' => '0427',
            'motivo_anulacion' => '  peso   registrado de forma incorrecta ',
            'anulada_por' => $otherUser->id,
            'anulada_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('cargas-proveedor.show', $load))
            ->assertSessionHas('status', "Carga {$load->numero_carga} anulada correctamente.");
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'anulada_por' => $user->id,
            'anulacion_autorizada_por' => $administrator->id,
            'anulada_at' => '2026-08-29 16:45:10',
            'motivo_anulacion' => 'peso registrado de forma incorrecta',
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 0,
            'kg_disponibles' => 0,
        ]);
        $this->assertDatabaseHas('vw_saldos_carga_proveedor', [
            'carga_id' => $load->id,
            'total_pagado' => 0,
            'saldo_pendiente' => 0,
            'estado_pago' => 'ANULADA',
            'requiere_alerta' => 0,
        ]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $user->id,
            'tabla_afectada' => 'cargas_proveedor',
            'registro_id' => $load->id,
            'accion' => 'ANULAR',
        ]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'motivo_anulacion',
            'valor_nuevo' => 'peso registrado de forma incorrecta',
        ]);
        $this->actingAs($user)
            ->get(route('cargas-proveedor.show', $load))
            ->assertOk()
            ->assertSee('Carga anulada')
            ->assertSee("Autorizada con PIN por {$administrator->nombreCompleto()}")
            ->assertSee('peso registrado de forma incorrecta');
    }

    public function test_wrong_administrator_pin_does_not_cancel_the_load_or_flash_the_pin(): void
    {
        $user = $this->userWithPermission();
        $administrator = $this->administratorWithPin();
        $load = CargaProveedor::factory()->create();

        $this->actingAs($user)
            ->from(route('cargas-proveedor.anulacion.create', $load))
            ->post(route('cargas-proveedor.anulacion.store', $load), [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => '9999',
                'motivo_anulacion' => 'La carga fue registrada con información incorrecta.',
            ])
            ->assertSessionHasErrors([
                'pin_autorizacion' => 'El administrador o el PIN no son correctos.',
            ]);

        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'anulada_at' => null,
            'anulacion_autorizada_por' => null,
        ]);
        $this->assertArrayNotHasKey('pin_autorizacion', session('_old_input', []));
    }

    public function test_load_with_active_payment_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_total' => 500]);
        PagoProveedor::factory()->create([
            'carga_id' => $load->id,
            'monto' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('cargas-proveedor.anulacion.create', $load))
            ->assertStatus(409);

        $this->actingAs($user)
            ->from(route('cargas-proveedor.show', $load))
            ->post(route('cargas-proveedor.anulacion.store', $load), [
                'motivo_anulacion' => 'La carga necesita una corrección completa.',
            ])
            ->assertSessionHasErrors([
                'motivo_anulacion' => 'Anula primero los pagos vigentes de la carga.',
            ]);
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'anulada_at' => null,
        ]);
    }

    public function test_load_with_active_beneficiary_process_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermission();
        $sourceProduct = Producto::factory()->create();
        $destinationProduct = Producto::factory()->soloPeso()->create();
        $load = CargaProveedor::factory()->create([
            'producto_id' => $sourceProduct->id,
            'fecha_carga' => today(),
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => 100,
            'peso_bruto_kg' => 250,
        ]);
        ProcesoBeneficiado::factory()->create([
            'carga_proveedor_id' => $load->id,
            'producto_destino_id' => $destinationProduct->id,
        ]);

        $this->actingAs($user)
            ->get(route('cargas-proveedor.anulacion.create', $load))
            ->assertStatus(409);

        $this->actingAs($user)
            ->from(route('cargas-proveedor.show', $load))
            ->post(route('cargas-proveedor.anulacion.store', $load), [
                'motivo_anulacion' => 'La carga necesita una corrección completa.',
            ])
            ->assertSessionHasErrors([
                'motivo_anulacion' => 'Anula primero los procesos de beneficiado vigentes de la carga.',
            ]);
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'anulada_at' => null,
        ]);
    }

    public function test_cancelled_load_cannot_be_cancelled_again(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->anulada($user)->create();

        $this->actingAs($user)
            ->from(route('cargas-proveedor.show', $load))
            ->post(route('cargas-proveedor.anulacion.store', $load), [
                'motivo_anulacion' => 'Segundo intento de anulación de la carga.',
            ])
            ->assertSessionHasErrors([
                'motivo_anulacion' => 'Esta carga ya fue anulada.',
            ]);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::query()->firstOrCreate(
            ['codigo' => 'CARGAS_ANULAR'],
            ['nombre' => 'Anular cargas de proveedor'],
        );

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }

    private function administratorWithPin(string $pin = '0427'): Usuario
    {
        $administrator = Usuario::factory()->create([
            'pin_autorizacion_hash' => $pin,
        ]);
        $role = Rol::factory()->create([
            'nombre' => 'ADMINISTRADOR',
            'activo' => true,
        ]);
        $administrator->roles()->attach($role);

        return $administrator;
    }
}
