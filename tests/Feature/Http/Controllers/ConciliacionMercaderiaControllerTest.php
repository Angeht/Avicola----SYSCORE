<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AjusteMercaderia;
use App\Models\ConciliacionMercaderia;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConciliacionMercaderiaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('conciliaciones-mercaderia.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_reconciliation_permission_is_forbidden(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->get(route('conciliaciones-mercaderia.index'))
            ->assertForbidden();
    }

    public function test_create_form_lists_active_products_with_their_current_stock(): void
    {
        $user = $this->userWithPermission();
        $activeProduct = Producto::factory()->create(['nombre' => 'POLLO PARA CONTEO']);
        Producto::factory()->inactivo()->create(['nombre' => 'PRODUCTO INACTIVO']);
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $this->createAdjustment($activeProduct, $entryType, 100, '200.500', $user);

        $this->actingAs($user)
            ->get(route('conciliaciones-mercaderia.create', ['producto' => $activeProduct->id]))
            ->assertOk()
            ->assertSee('POLLO PARA CONTEO')
            ->assertSee('100 aves')
            ->assertSee('200,500 kg')
            ->assertSee('value="'.$activeProduct->id.'"', false)
            ->assertDontSee('PRODUCTO INACTIVO');
    }

    public function test_balanced_count_creates_reconciliation_without_adjustments_and_uses_server_audit_data(): void
    {
        $this->travelTo('2026-08-28 09:15:30');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $this->createAdjustment($product, $entryType, 100, '200.500', $user);

        $response = $this->actingAs($user)->post(route('conciliaciones-mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_conciliacion' => 'cierre',
            'cantidad_pollos_fisico' => 100,
            'peso_fisico_kg' => '200,500',
            'observacion' => '  cierre   físico sin diferencias  ',
            'numero_conciliacion' => 'MANIPULADA',
            'usuario_id' => $otherUser->id,
            'cantidad_pollos_sistema' => 1,
            'peso_sistema_kg' => 1,
            'realizada_at' => '2000-01-01 00:00:00',
        ]);

        $conciliation = ConciliacionMercaderia::query()->firstOrFail();
        $expectedNumber = sprintf('CON-20260828-%06d', $conciliation->id);

        $response
            ->assertRedirect(route('conciliaciones-mercaderia.show', $conciliation))
            ->assertSessionHas('status', "Conciliación $expectedNumber registrada correctamente.");
        $this->assertDatabaseHas('conciliaciones_mercaderia', [
            'id' => $conciliation->id,
            'numero_conciliacion' => $expectedNumber,
            'producto_id' => $product->id,
            'fecha_operacion' => '2026-08-28',
            'tipo_conciliacion' => 'CIERRE',
            'usuario_id' => $user->id,
            'cantidad_pollos_sistema' => 100,
            'peso_sistema_kg' => 200.500,
            'cantidad_pollos_fisico' => 100,
            'peso_fisico_kg' => 200.500,
            'observacion' => 'cierre físico sin diferencias',
            'realizada_at' => '2026-08-28 09:15:30',
        ]);
        $this->assertDatabaseMissing('conciliaciones_mercaderia', [
            'id' => $conciliation->id,
            'usuario_id' => $otherUser->id,
        ]);
        $this->assertDatabaseCount('conciliacion_ajuste', 0);
        $this->assertDatabaseCount('ajustes_mercaderia', 1);
    }

    public function test_negative_difference_creates_a_linked_adjustment_and_matches_physical_stock(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $negativeType = TipoAjusteMercaderia::factory()->salida()->create(['codigo' => 'AJUSTE_NEGATIVO']);
        $this->createAdjustment($product, $entryType, 100, '200.000', $user);

        $this->actingAs($user)->post(route('conciliaciones-mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_conciliacion' => 'CIERRE',
            'cantidad_pollos_fisico' => 94,
            'peso_fisico_kg' => '187.250',
            'observacion' => null,
        ])->assertRedirect();

        $conciliation = ConciliacionMercaderia::query()->firstOrFail();
        $adjustment = AjusteMercaderia::query()->where('tipo_ajuste_id', $negativeType->id)->firstOrFail();
        $this->assertDatabaseHas('ajustes_mercaderia', [
            'id' => $adjustment->id,
            'producto_id' => $product->id,
            'cantidad_pollos' => 6,
            'peso_kg' => 12.750,
            'usuario_id' => $user->id,
            'anulado_at' => null,
        ]);
        $this->assertDatabaseHas('conciliacion_ajuste', [
            'conciliacion_id' => $conciliation->id,
            'ajuste_id' => $adjustment->id,
        ]);
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 94,
            'kg_disponibles' => 187.250,
        ]);
    }

    public function test_mixed_differences_create_positive_and_negative_adjustments(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        $positiveType = TipoAjusteMercaderia::factory()->create([
            'codigo' => 'AJUSTE_POSITIVO',
            'naturaleza' => 'ENTRADA',
        ]);
        $negativeType = TipoAjusteMercaderia::factory()->salida()->create(['codigo' => 'AJUSTE_NEGATIVO']);
        $this->createAdjustment($product, $entryType, 100, '200.000', $user);

        $this->actingAs($user)->post(route('conciliaciones-mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_conciliacion' => 'EXTRAORDINARIA',
            'cantidad_pollos_fisico' => 110,
            'peso_fisico_kg' => '190.000',
            'observacion' => 'Reconteo por diferencia de aves y peso.',
        ])->assertRedirect();

        $conciliation = ConciliacionMercaderia::query()->firstOrFail();
        $this->assertDatabaseHas('ajustes_mercaderia', [
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $positiveType->id,
            'cantidad_pollos' => 10,
            'peso_kg' => 0,
        ]);
        $this->assertDatabaseHas('ajustes_mercaderia', [
            'producto_id' => $product->id,
            'tipo_ajuste_id' => $negativeType->id,
            'cantidad_pollos' => 0,
            'peso_kg' => 10,
        ]);
        $this->assertDatabaseCount('conciliacion_ajuste', 2);
        $this->assertSame(2, $conciliation->ajustes()->count());
        $this->assertDatabaseHas('vw_saldo_mercaderia_actual', [
            'producto_id' => $product->id,
            'pollos_disponibles' => 110,
            'kg_disponibles' => 190,
        ]);
    }

    public function test_extraordinary_reconciliation_requires_an_explanation(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();

        $response = $this->actingAs($user)->post(route('conciliaciones-mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_conciliacion' => 'EXTRAORDINARIA',
            'cantidad_pollos_fisico' => 0,
            'peso_fisico_kg' => 0,
            'observacion' => null,
        ]);

        $response->assertSessionHasErrors([
            'observacion' => 'Explica el motivo de la conciliación extraordinaria.',
        ]);
        $this->assertDatabaseCount('conciliaciones_mercaderia', 0);
    }

    public function test_reconciliation_is_rejected_when_automatic_adjustment_type_is_unavailable(): void
    {
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $entryType = TipoAjusteMercaderia::factory()->create(['naturaleza' => 'ENTRADA']);
        TipoAjusteMercaderia::factory()->salida()->inactivo()->create(['codigo' => 'AJUSTE_NEGATIVO']);
        $this->createAdjustment($product, $entryType, 10, '20.000', $user);

        $response = $this->actingAs($user)->post(route('conciliaciones-mercaderia.store'), [
            'producto_id' => $product->id,
            'tipo_conciliacion' => 'CIERRE',
            'cantidad_pollos_fisico' => 9,
            'peso_fisico_kg' => '19.000',
            'observacion' => null,
        ]);

        $response->assertSessionHasErrors([
            'producto_id' => 'Los tipos de ajuste automático no están disponibles. Comunícate con un administrador.',
        ]);
        $this->assertDatabaseCount('conciliaciones_mercaderia', 0);
    }

    public function test_index_filters_records_and_show_escapes_observations(): void
    {
        $user = $this->userWithPermission();
        $matchingProduct = Producto::factory()->create(['nombre' => 'POLLO ESPECIAL']);
        $otherProduct = Producto::factory()->create(['nombre' => 'POLLO ESTÁNDAR']);
        $dangerousObservation = '<script>alert("conteo")</script> revisión especial';
        $matching = ConciliacionMercaderia::factory()->create([
            'producto_id' => $matchingProduct->id,
            'usuario_id' => $user->id,
            'observacion' => $dangerousObservation,
        ]);
        $other = ConciliacionMercaderia::factory()->create([
            'producto_id' => $otherProduct->id,
            'usuario_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('conciliaciones-mercaderia.index', ['buscar' => 'especial']))
            ->assertOk()
            ->assertSee($matching->numero_conciliacion)
            ->assertDontSee($other->numero_conciliacion);

        $this->actingAs($user)
            ->get(route('conciliaciones-mercaderia.show', $matching))
            ->assertOk()
            ->assertSee($dangerousObservation)
            ->assertDontSee($dangerousObservation, false);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'MERCADERIA_CONCILIAR']);

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
