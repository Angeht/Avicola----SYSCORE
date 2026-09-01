<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CargaProveedorControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cargas-proveedor.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_load_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();

        $this->actingAs($user)
            ->get(route('cargas-proveedor.index'))
            ->assertForbidden();
    }

    public function test_create_form_only_lists_active_providers_and_products(): void
    {
        $user = $this->userWithPermission();
        $activeProvider = Proveedor::factory()->create(['nombre_razon_social' => 'GRANJA ACTIVA']);
        Proveedor::factory()->inactivo()->create(['nombre_razon_social' => 'GRANJA INACTIVA']);
        $activeProduct = Producto::factory()->create(['nombre' => 'POLLO ACTIVO']);
        Producto::factory()->inactivo()->create(['nombre' => 'POLLO INACTIVO']);

        $response = $this->actingAs($user)->get(route('cargas-proveedor.create'));

        $response
            ->assertOk()
            ->assertSee($activeProvider->nombre_razon_social)
            ->assertSee($activeProduct->nombre)
            ->assertSee('Costo por kg')
            ->assertDontSee('GRANJA INACTIVA')
            ->assertDontSee('POLLO INACTIVO');
    }

    public function test_valid_payload_creates_authorized_load_before_any_weighing(): void
    {
        $this->travelTo('2026-08-27 08:35:20');
        $user = $this->userWithPermission();
        $otherUser = Usuario::factory()->create();
        $provider = Proveedor::factory()->create();
        $product = Producto::factory()->create();

        $response = $this->actingAs($user)->post(route('cargas-proveedor.store'), [
            'numero_carga' => 'MANIPULADA',
            'recibido_por' => $otherUser->id,
            'proveedor_id' => $provider->id,
            'producto_id' => $product->id,
            'fecha_carga' => '2026-08-27',
            'costo_kg' => '7,2500',
            'costo_total' => '999999.99',
            'observacion' => '  guía   008 ',
            'pesajes' => [['carga_id' => 999]],
        ]);

        $load = CargaProveedor::query()->firstOrFail();
        $expectedNumber = sprintf('CAR-20260827-%06d', $load->id);
        $response
            ->assertRedirect(route('cargas-proveedor.pesajes.create', $load))
            ->assertSessionHas('status', "Carga $expectedNumber creada. Ahora registra sus pesajes.");
        $this->assertSame($expectedNumber, $load->numero_carga);
        $this->assertSame($user->id, $load->recibido_por);
        $this->assertSame('guía 008', $load->observacion);
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'numero_carga' => $expectedNumber,
            'proveedor_id' => $provider->id,
            'producto_id' => $product->id,
            'fecha_carga' => '2026-08-27',
            'costo_kg' => 7.2500,
            'costo_total' => 0,
            'recibido_por' => $user->id,
        ]);
        $this->assertDatabaseCount('pesajes_carga', 0);
    }

    public function test_required_load_fields_are_rejected(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)
            ->from(route('cargas-proveedor.create'))
            ->post(route('cargas-proveedor.store'), []);

        $response->assertSessionHasErrors([
            'proveedor_id' => 'Selecciona el proveedor.',
            'producto_id' => 'Selecciona el producto recibido.',
            'fecha_carga' => 'Indica la fecha de recepción.',
            'costo_kg' => 'Ingresa el costo por kilogramo.',
        ]);
        $this->assertDatabaseCount('cargas_proveedor', 0);
    }

    public function test_future_load_date_is_rejected(): void
    {
        $this->travelTo('2026-08-27 08:00:00');
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)
            ->from(route('cargas-proveedor.create'))
            ->post(route('cargas-proveedor.store'), $this->validPayload([
                'fecha_carga' => '2026-08-28',
            ]));

        $response->assertSessionHasErrors([
            'fecha_carga' => 'La fecha de recepción no puede ser futura.',
        ]);
        $this->assertDatabaseCount('cargas_proveedor', 0);
    }

    public function test_index_filters_loads_by_provider_and_shows_calculated_summary(): void
    {
        $user = $this->userWithPermission();
        $matchingProvider = Proveedor::factory()->create(['nombre_razon_social' => 'GRANJA EL SOL']);
        $otherProvider = Proveedor::factory()->create(['nombre_razon_social' => 'AVES DEL NORTE']);
        $matchingLoad = CargaProveedor::factory()->create([
            'numero_carga' => 'CAR-20260827-000111',
            'proveedor_id' => $matchingProvider->id,
            'costo_kg' => 7.25,
        ]);
        $otherLoad = CargaProveedor::factory()->create([
            'numero_carga' => 'CAR-20260827-000222',
            'proveedor_id' => $otherProvider->id,
        ]);
        PesajeCarga::factory()->create([
            'carga_id' => $matchingLoad->id,
            'cantidad_pollos' => 80,
            'peso_bruto_kg' => 220,
            'cantidad_jabas' => 10,
            'tara_unitaria_aplicada_kg' => 2,
        ]);
        PesajeCarga::factory()->create(['carga_id' => $otherLoad->id]);

        $response = $this->actingAs($user)->get(route('cargas-proveedor.index', [
            'buscar' => 'el sol',
        ]));

        $response
            ->assertOk()
            ->assertSee($matchingLoad->numero_carga)
            ->assertSee('200,000 kg')
            ->assertSee('S/ 7,25 / kg')
            ->assertDontSee($otherLoad->numero_carga);
    }

    public function test_show_renders_load_traceability_and_escapes_observations(): void
    {
        $user = $this->userWithPermission();
        $dangerousText = '<script>alert("carga")</script>';
        $load = CargaProveedor::factory()->create(['observacion' => $dangerousText]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'observacion' => $dangerousText,
        ]);
        $providerName = $load->proveedor()->value('nombre_razon_social');

        $response = $this->actingAs($user)->get(route('cargas-proveedor.show', $load));

        $response
            ->assertOk()
            ->assertSee($load->numero_carga)
            ->assertSee($providerName)
            ->assertSee('Costo por kg')
            ->assertSee($dangerousText)
            ->assertDontSee($dangerousText, false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'proveedor_id' => Proveedor::factory()->create()->id,
            'producto_id' => Producto::factory()->create()->id,
            'fecha_carga' => today()->toDateString(),
            'costo_kg' => '7.2500',
            'observacion' => null,
        ], $overrides);
    }

    private function userWithPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'CARGAS_REGISTRAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
