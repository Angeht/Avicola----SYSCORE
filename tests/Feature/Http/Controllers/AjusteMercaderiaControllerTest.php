<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteMercaderia;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AjusteMercaderiaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('mercaderia.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_merchandise_permission_is_forbidden(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->get(route('mercaderia.index'))
            ->assertForbidden();
    }

    public function test_create_form_lists_only_active_products_and_adjustment_types(): void
    {
        $user = $this->userWithPermission();
        $activeProduct = Producto::factory()->create(['nombre' => 'POLLO ACTIVO']);
        Producto::factory()->inactivo()->create(['nombre' => 'POLLO INACTIVO']);
        $activeType = TipoAjusteMercaderia::factory()->create(['nombre' => 'AJUSTE ACTIVO']);
        TipoAjusteMercaderia::factory()->inactivo()->create(['nombre' => 'AJUSTE INACTIVO']);
        $this->createAdjustment($activeProduct, $activeType, 75, '120.500', $user);

        $this->actingAs($user)
            ->get(route('mercaderia.create', ['producto' => $activeProduct->id]))
            ->assertOk()
            ->assertSee('POLLO ACTIVO')
            ->assertSee('AJUSTE ACTIVO')
            ->assertSee('75 aves')
            ->assertSee('120,500 kg')
            ->assertSee('value="'.$activeProduct->id.'"', false)
            ->assertDontSee('POLLO INACTIVO')
            ->assertDontSee('AJUSTE INACTIVO');
    }

    public function test_valid_entry_creates_server_audit_data_and_increases_stock(): void
    {
        $this->travelTo('2026-08-27 15:45:12');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $product = Producto::factory()->create();
        $type = TipoAjusteMercaderia::factory()->create([
            'codigo' => 'AJUSTE_POSITIVO_TEST',
            'naturaleza' => 'ENTRADA',
        ]);

        $response = $this->actingAs($user)->post(route('mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $type->id,
            'cantidad_pollos' => '25',
            'peso_kg' => '48,750',
            'motivo' => '  corrección   por conteo físico  ',
            'numero_ajuste' => 'MANIPULADO',
            'usuario_id' => $otherUser->id,
            'fecha_ajuste' => '2000-01-01 00:00:00',
            'anulado_por' => $otherUser->id,
        ]);

        $adjustment = AjusteMercaderia::query()->firstOrFail();
        $expectedNumber = sprintf('AJU-20260827-%06d', $adjustment->id);

        $response
            ->assertRedirect(route('mercaderia.show', $adjustment))
            ->assertSessionHas('status', "Ajuste $expectedNumber registrado correctamente.");
        $this->assertDatabaseHas('ajustes_mercaderia', [
            'id' => $adjustment->id,
            'numero_ajuste' => $expectedNumber,
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $type->id,
            'cantidad_pollos' => 25,
            'peso_kg' => 48.750,
            'motivo' => 'corrección por conteo físico',
            'usuario_id' => $user->id,
            'fecha_ajuste' => '2026-08-27 15:45:12',
            'anulado_at' => null,
        ]);
        $this->assertDatabaseMissing('ajustes_mercaderia', [
            'id' => $adjustment->id,
            'usuario_id' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 25,
            'kg_disponibles' => 48.750,
        ]);
    }

    public function test_valid_outgoing_adjustment_decreases_stock(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $outgoingType = TipoAjusteMercaderia::factory()->salida()->create();
        $this->createAdjustment($product, $entryType, 100, '200.000', $user);

        $this->actingAs($user)->post(route('mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $outgoingType->id,
            'cantidad_pollos' => 20,
            'peso_kg' => '35.250',
            'motivo' => 'Merma detectada durante el control.',
        ])->assertRedirect();

        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 80,
            'kg_disponibles' => 164.750,
        ]);
    }

    public function test_adjustment_requires_birds_or_weight_greater_than_zero(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $type = TipoAjusteMercaderia::factory()->create();

        $response = $this->actingAs($user)->post(route('mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $type->id,
            'cantidad_pollos' => 0,
            'peso_kg' => '0.000',
            'motivo' => 'Control sin ninguna diferencia.',
        ]);

        $response->assertSessionHasErrors([
            'cantidad_pollos' => 'Ingresa al menos una cantidad de aves o un peso mayor que cero.',
        ]);
        $this->assertDatabaseCount('ajustes_mercaderia', 0);
    }

    public function test_outgoing_adjustment_cannot_exceed_available_stock(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $outgoingType = TipoAjusteMercaderia::factory()->salida()->create();
        $this->createAdjustment($product, $entryType, 10, '20.000', $user);

        $response = $this->actingAs($user)->post(route('mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $outgoingType->id,
            'cantidad_pollos' => 11,
            'peso_kg' => '20.001',
            'motivo' => 'Salida superior para validar control.',
        ]);

        $response->assertSessionHasErrors([
            'cantidad_pollos' => 'La salida supera las aves disponibles del producto.',
            'peso_kg' => 'La salida supera el peso disponible del producto.',
        ]);
        $this->assertDatabaseCount('ajustes_mercaderia', 1);
    }

    public function test_initial_balance_is_rejected_after_the_first_movement(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $initialType = TipoAjusteMercaderia::factory()->create([
            'codigo' => 'SALDO_INICIAL',
            'naturaleza' => 'ENTRADA',
        ]);
        $this->createAdjustment($product, $entryType, 10, '20.000', $user);

        $response = $this->actingAs($user)->post(route('mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $initialType->id,
            'cantidad_pollos' => 50,
            'peso_kg' => '100.000',
            'motivo' => 'Carga tardía del saldo inicial.',
        ]);

        $response->assertSessionHasErrors([
            'tipo_ajuste_id' => 'El saldo inicial solo puede registrarse antes del primer movimiento del producto.',
        ]);
        $this->assertDatabaseCount('ajustes_mercaderia', 1);
    }

    public function test_index_filters_adjustments_and_show_escapes_the_reason(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create(['nombre' => 'POLLO ESPECIAL']);
        $otherProduct = Producto::factory()->create(['nombre' => 'POLLO CORRIENTE']);
        $type = TipoAjusteMercaderia::factory()->create();
        $dangerousReason = '<script>alert("ajuste")</script> diferencia especial';
        $matchingAdjustment = $this->createAdjustment($product, $type, 10, '20.000', $user, $dangerousReason);
        $otherAdjustment = $this->createAdjustment($otherProduct, $type, 5, '10.000', $user);

        $this->actingAs($user)
            ->get(route('mercaderia.index', ['buscar' => 'especial']))
            ->assertOk()
            ->assertSee($matchingAdjustment->numero_ajuste)
            ->assertDontSee($otherAdjustment->numero_ajuste);

        $this->actingAs($user)
            ->get(route('mercaderia.show', $matchingAdjustment))
            ->assertOk()
            ->assertSee($dangerousReason)
            ->assertDontSee($dangerousReason, false);
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
        string $reason = 'Ajuste de preparación para la prueba.',
    ): AjusteMercaderia {
        return AjusteMercaderia::factory()->create([
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $type->id,
            'cantidad_pollos' => $birds,
            'peso_kg' => $kilograms,
            'motivo' => $reason,
            'usuario_id' => $user->id,
        ]);
    }
}
