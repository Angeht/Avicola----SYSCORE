<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Permiso;
use App\Models\PrecioDia;
use App\Models\PrecioDiaVersion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PrecioDiaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('precios-dia.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_price_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('precios-dia.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_filter_daily_prices_by_product(): void
    {
        $this->travelTo('2026-08-27 09:00:00');
        $user = $this->userWithPermission();
        $chicken = Producto::factory()->create(['nombre' => 'POLLO ENTERO']);
        $otherProduct = Producto::factory()->create(['nombre' => 'GALLINA']);
        $chickenPrice = PrecioDia::factory()->create(['producto_id' => $chicken->id]);
        $otherPrice = PrecioDia::factory()->create(['producto_id' => $otherProduct->id]);
        PrecioDiaVersion::factory()->create(['precio_dia_id' => $chickenPrice->id, 'precio_kg' => 7.25]);
        PrecioDiaVersion::factory()->create(['precio_dia_id' => $otherPrice->id, 'precio_kg' => 6.10]);

        $response = $this->actingAs($user)->get(route('precios-dia.index', [
            'buscar' => 'pollo',
            'fecha' => '2026-08-27',
        ]));

        $response
            ->assertOk()
            ->assertSee('POLLO ENTERO')
            ->assertDontSee('GALLINA')
            ->assertSee('S/ 7,2500');
    }

    public function test_valid_payload_creates_initial_price_version_for_authenticated_user(): void
    {
        $this->travelTo('2026-08-27 10:15:20');
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $otherUser = Usuario::factory()->create();

        $response = $this->actingAs($user)->post(route('precios-dia.store'), [
            'producto_id' => $product->id,
            'fecha' => '2026-08-27',
            'precio_kg' => '7.2500',
            'motivo_cambio' => null,
            'registrado_por' => $otherUser->id,
            'vigente_desde' => '2000-01-01 00:00:00',
        ]);

        $priceDay = PrecioDia::query()->where('producto_id', $product->id)->firstOrFail();
        $response
            ->assertRedirect(route('precios-dia.show', $priceDay))
            ->assertSessionHas('status', 'Precio del día registrado correctamente.');
        $this->assertDatabaseHas('precios_dia', [
            'producto_id' => $product->id,
            'fecha' => '2026-08-27',
        ]);
        $this->assertDatabaseHas('precio_dia_versiones', [
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => 7.2500,
            'registrado_por' => $user->id,
            'vigente_desde' => '2026-08-27 10:15:20',
        ]);
        $this->assertDatabaseMissing('precio_dia_versiones', ['registrado_por' => $otherUser->id]);
    }

    public function test_price_change_creates_an_immutable_version_and_preserves_previous_value(): void
    {
        $this->travelTo('2026-08-27 08:00:00');
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $priceDay = PrecioDia::factory()->create(['producto_id' => $product->id]);
        $initialVersion = PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => 7.10,
            'registrado_por' => $user->id,
        ]);
        $this->travelTo('2026-08-27 11:30:00');

        $response = $this->actingAs($user)->post(route('precios-dia.store'), [
            'producto_id' => $product->id,
            'fecha' => '2026-08-27',
            'precio_kg' => '7.45',
            'motivo_cambio' => '  variación   del mercado ',
        ]);

        $response->assertRedirect(route('precios-dia.show', $priceDay));
        $this->assertDatabaseCount('precios_dia', 1);
        $this->assertDatabaseCount('precio_dia_versiones', 2);
        $this->assertDatabaseHas('precio_dia_versiones', [
            'id' => $initialVersion->id,
            'precio_kg' => 7.1000,
        ]);
        $this->assertDatabaseHas('precio_dia_versiones', [
            'precio_dia_id' => $priceDay->id,
            'precio_kg' => 7.4500,
            'motivo_cambio' => 'variación del mercado',
        ]);
    }

    public function test_price_change_requires_a_reason(): void
    {
        $this->travelTo('2026-08-27 09:00:00');
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $priceDay = PrecioDia::factory()->create(['producto_id' => $product->id]);
        PrecioDiaVersion::factory()->create(['precio_dia_id' => $priceDay->id, 'precio_kg' => 7.10]);

        $response = $this->actingAs($user)
            ->from(route('precios-dia.create'))
            ->post(route('precios-dia.store'), [
                'producto_id' => $product->id,
                'fecha' => '2026-08-27',
                'precio_kg' => '7.40',
            ]);

        $response->assertSessionHasErrors([
            'motivo_cambio' => 'Explica el motivo del cambio de precio.',
        ]);
        $this->assertDatabaseCount('precio_dia_versiones', 1);
    }

    public function test_same_price_is_rejected_without_creating_a_redundant_version(): void
    {
        $this->travelTo('2026-08-27 09:00:00');
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();
        $priceDay = PrecioDia::factory()->create(['producto_id' => $product->id]);
        PrecioDiaVersion::factory()->create(['precio_dia_id' => $priceDay->id, 'precio_kg' => 7.10]);

        $response = $this->actingAs($user)
            ->from(route('precios-dia.create'))
            ->post(route('precios-dia.store'), [
                'producto_id' => $product->id,
                'fecha' => '2026-08-27',
                'precio_kg' => '7.1000',
                'motivo_cambio' => 'Intento duplicado',
            ]);

        $response->assertSessionHasErrors([
            'precio_kg' => 'El nuevo precio debe ser diferente al precio vigente.',
        ]);
        $this->assertDatabaseCount('precio_dia_versiones', 1);
    }

    public function test_price_can_only_be_registered_for_current_operational_date(): void
    {
        $this->travelTo('2026-08-27 09:00:00');
        $user = $this->userWithPermission();
        $product = Producto::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('precios-dia.create'))
            ->post(route('precios-dia.store'), [
                'producto_id' => $product->id,
                'fecha' => '2026-08-28',
                'precio_kg' => '7.50',
            ]);

        $response->assertSessionHasErrors([
            'fecha' => 'Solo puedes registrar precios para la fecha actual.',
        ]);
        $this->assertDatabaseCount('precios_dia', 0);
    }

    public function test_price_history_escapes_change_reason(): void
    {
        $this->travelTo('2026-08-27 09:00:00');
        $user = $this->userWithPermission();
        $priceDay = PrecioDia::factory()->create();
        PrecioDiaVersion::factory()->create([
            'precio_dia_id' => $priceDay->id,
            'motivo_cambio' => '<script>alert("precio")</script>',
        ]);

        $response = $this->actingAs($user)->get(route('precios-dia.show', $priceDay));

        $response
            ->assertOk()
            ->assertSee('<script>alert("precio")</script>')
            ->assertDontSee('<script>alert("precio")</script>', false);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'PRECIO_DIA_GESTIONAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
