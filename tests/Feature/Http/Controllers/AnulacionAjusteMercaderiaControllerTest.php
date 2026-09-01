<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteMercaderia;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnulacionAjusteMercaderiaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $adjustment = AjusteMercaderia::factory()->create();

        $this->get(route('mercaderia.anulacion.create', $adjustment))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_merchandise_permission_is_forbidden(): void
    {
        $adjustment = AjusteMercaderia::factory()->create();

        $this->actingAs(Usuario::factory()->create())
            ->get(route('mercaderia.anulacion.create', $adjustment))
            ->assertForbidden();
    }

    public function test_reason_must_have_at_least_ten_characters(): void
    {
        $user = $this->userWithPermission();
        $adjustment = AjusteMercaderia::factory()->create();

        $response = $this->actingAs($user)->post(route('mercaderia.anulacion.store', $adjustment), [
            'motivo_anulacion' => 'Error',
        ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'El motivo debe tener al menos 10 caracteres.',
        ]);
        $this->assertDatabaseHas('ajustes_mercaderia', ['id' => $adjustment->id, 'anulado_at' => null]);
    }

    public function test_valid_outgoing_cancellation_restores_stock_and_uses_server_audit_data(): void
    {
        $this->travelTo('2026-08-27 19:10:05');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $outgoingType = TipoAjusteMercaderia::factory()->salida()->create();
        $this->createAdjustment($product, $entryType, 100, '200.000', $user);
        $outgoingAdjustment = $this->createAdjustment($product, $outgoingType, 25, '45.500', $user);

        $response = $this->actingAs($user)->post(route('mercaderia.anulacion.store', $outgoingAdjustment), [
            'motivo_anulacion' => '  movimiento   duplicado por error  ',
            'anulado_por' => $otherUser->id,
            'anulado_at' => '2000-01-01 00:00:00',
        ]);

        $response
            ->assertRedirect(route('mercaderia.show', $outgoingAdjustment))
            ->assertSessionHas('status', "Ajuste {$outgoingAdjustment->numero_ajuste} anulado correctamente.");
        $this->assertDatabaseHas('ajustes_mercaderia', [
            'id' => $outgoingAdjustment->id,
            'anulado_por' => $user->id,
            'anulado_at' => '2026-08-27 19:10:05',
            'motivo_anulacion' => 'movimiento duplicado por error',
        ]);
        $this->assertDatabaseMissing('ajustes_mercaderia', [
            'id' => $outgoingAdjustment->id,
            'anulado_por' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 100,
            'kg_disponibles' => 200.000,
        ]);
    }

    public function test_cancelled_adjustment_cannot_be_cancelled_again(): void
    {
        $user = $this->userWithPermission();
        $adjustment = AjusteMercaderia::factory()->anulado()->create();

        $response = $this->actingAs($user)->post(route('mercaderia.anulacion.store', $adjustment), [
            'motivo_anulacion' => 'Segundo intento de anulación.',
        ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'Este ajuste ya fue anulado.',
        ]);
    }

    public function test_incoming_adjustment_cannot_be_cancelled_after_stock_was_used(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $outgoingType = TipoAjusteMercaderia::factory()->salida()->create();
        $entryAdjustment = $this->createAdjustment($product, $entryType, 100, '200.000', $user);
        $this->createAdjustment($product, $outgoingType, 80, '160.000', $user);

        $this->actingAs($user)
            ->get(route('mercaderia.anulacion.create', $entryAdjustment))
            ->assertStatus(409);

        $response = $this->actingAs($user)->post(route('mercaderia.anulacion.store', $entryAdjustment), [
            'motivo_anulacion' => 'Intento de retirar entrada consumida.',
        ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'No puedes anular esta entrada porque la mercadería ya fue utilizada.',
        ]);
        $this->assertDatabaseHas('ajustes_mercaderia', ['id' => $entryAdjustment->id, 'anulado_at' => null]);
    }

    public function test_adjustment_linked_to_a_reconciliation_cannot_be_cancelled(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $type = TipoAjusteMercaderia::factory()->create();
        $adjustment = $this->createAdjustment($product, $type, 10, '20.000', $user);
        $conciliationId = DB::table('conciliaciones_mercaderia')->insertGetId([
            'numero_conciliacion' => 'CON-20260827-000001',
            'producto_id' => $product->id,
            'fecha_operacion' => '2026-08-27',
            'tipo_conciliacion' => 'EXTRAORDINARIA',
            'usuario_id' => $user->id,
            'cantidad_pollos_sistema' => 10,
            'peso_sistema_kg' => 20,
            'cantidad_pollos_fisico' => 10,
            'peso_fisico_kg' => 20,
            'observacion' => 'Conciliación de prueba.',
            'realizada_at' => now(),
        ]);
        DB::table('conciliacion_ajuste')->insert([
            'conciliacion_id' => $conciliationId,
            'ajuste_id' => $adjustment->id,
        ]);

        $this->actingAs($user)
            ->get(route('mercaderia.anulacion.create', $adjustment))
            ->assertStatus(409);

        $response = $this->actingAs($user)->post(route('mercaderia.anulacion.store', $adjustment), [
            'motivo_anulacion' => 'Intento de anular ajuste conciliado.',
        ]);

        $response->assertSessionHasErrors([
            'motivo_anulacion' => 'No puedes anular un ajuste vinculado a una conciliación de mercadería.',
        ]);
        $this->assertDatabaseHas('ajustes_mercaderia', ['id' => $adjustment->id, 'anulado_at' => null]);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'MERCADERIA_AJUSTAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }

    private function createAdjustment(
        Producto $product,
        TipoAjusteMercaderia $type,
        int $birds,
        string $kilograms,
        Usuario $user,
    ): AjusteMercaderia {
        return AjusteMercaderia::factory()->create([
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $type->id,
            'cantidad_pollos' => $birds,
            'peso_kg' => $kilograms,
            'usuario_id' => $user->id,
        ]);
    }
}
