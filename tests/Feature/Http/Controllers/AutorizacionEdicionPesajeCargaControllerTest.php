<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AutorizacionEdicionPesajeCargaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $weighing = PesajeCarga::factory()->create();

        $this->get(route('cargas-proveedor.pesajes.autorizacion.create', [$weighing->carga_id, $weighing]))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_load_permission_is_forbidden(): void
    {
        $weighing = PesajeCarga::factory()->create();

        $this->actingAs(Usuario::factory()->create())
            ->get(route('cargas-proveedor.pesajes.autorizacion.create', [$weighing->carga_id, $weighing]))
            ->assertForbidden();
    }

    public function test_weighing_from_another_load_is_not_found(): void
    {
        $operator = $this->userWithLoadPermission();
        $load = CargaProveedor::factory()->create();
        $otherWeighing = PesajeCarga::factory()->create();

        $this->actingAs($operator)
            ->get(route('cargas-proveedor.pesajes.autorizacion.create', [$load, $otherWeighing]))
            ->assertNotFound();
    }

    public function test_screen_lists_only_active_administrators_with_configured_pin(): void
    {
        $operator = $this->userWithLoadPermission();
        $administratorRole = $this->administratorRole();
        $eligibleAdministrator = $this->administrator($administratorRole, '0427', 'ADMIN ELEGIBLE');
        $this->administrator($administratorRole, null, 'ADMIN SIN PIN');
        $this->administrator($administratorRole, '1111', 'ADMIN INACTIVO', false);
        $weighing = PesajeCarga::factory()->create();

        $this->actingAs($operator)
            ->get(route('cargas-proveedor.pesajes.autorizacion.create', [$weighing->carga_id, $weighing]))
            ->assertOk()
            ->assertSee($eligibleAdministrator->nombres)
            ->assertDontSee('ADMIN SIN PIN')
            ->assertDontSee('ADMIN INACTIVO');
    }

    public function test_screen_is_available_for_a_load_with_active_payments(): void
    {
        $operator = $this->userWithLoadPermission();
        $administrator = $this->administrator($this->administratorRole(), '0427');
        $weighing = PesajeCarga::factory()->create();
        PagoProveedor::factory()->create([
            'carga_id' => $weighing->carga_id,
            'monto' => 100,
        ]);

        $this->actingAs($operator)
            ->get(route('cargas-proveedor.pesajes.autorizacion.create', [$weighing->carga_id, $weighing]))
            ->assertOk()
            ->assertSee($administrator->nombres)
            ->assertSee('Esta carga tiene pagos vigentes.');
    }

    public function test_administrator_without_available_pin_can_open_its_pin_configuration(): void
    {
        $administrator = $this->administrator($this->administratorRole(), null);
        $weighing = PesajeCarga::factory()->create();

        $this->actingAs($administrator)
            ->get(route('cargas-proveedor.pesajes.autorizacion.create', [$weighing->carga_id, $weighing]))
            ->assertOk()
            ->assertSee('No hay un PIN disponible')
            ->assertSee(route('usuarios.pin-autorizacion.edit', $administrator));
    }

    public function test_wrong_pin_is_rejected_without_granting_edit_access_or_flashing_the_pin(): void
    {
        $operator = $this->userWithLoadPermission();
        $administrator = $this->administrator($this->administratorRole(), '0427');
        $weighing = PesajeCarga::factory()->create();

        $response = $this->actingAs($operator)
            ->post(route('cargas-proveedor.pesajes.autorizacion.store', [$weighing->carga_id, $weighing]), [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => '9999',
            ]);

        $response->assertSessionHasErrors([
            'pin_autorizacion' => 'El administrador o el PIN no son correctos.',
        ]);
        $this->assertArrayNotHasKey('pin_autorizacion', session('_old_input', []));

        $this->get(route('cargas-proveedor.pesajes.edit', [$weighing->carga_id, $weighing]))
            ->assertRedirect(route('cargas-proveedor.pesajes.autorizacion.create', [$weighing->carga_id, $weighing]))
            ->assertSessionHasErrors('autorizacion');
    }

    public function test_valid_pin_grants_temporary_access_to_selected_weighing(): void
    {
        $operator = $this->userWithLoadPermission();
        $administrator = $this->administrator($this->administratorRole(), '0427');
        $weighing = PesajeCarga::factory()->create();

        $response = $this->actingAs($operator)
            ->post(route('cargas-proveedor.pesajes.autorizacion.store', [$weighing->carga_id, $weighing]), [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => '0427',
            ]);

        $response->assertRedirect(route('cargas-proveedor.pesajes.edit', [$weighing->carga_id, $weighing]));
        $this->get(route('cargas-proveedor.pesajes.edit', [$weighing->carga_id, $weighing]))
            ->assertOk()
            ->assertSee($administrator->nombreCompleto())
            ->assertSee('Guardar corrección');
    }

    public function test_pin_attempts_are_rate_limited_after_five_failures(): void
    {
        $operator = $this->userWithLoadPermission();
        $administrator = $this->administrator($this->administratorRole(), '0427');
        $weighing = PesajeCarga::factory()->create();
        $route = route('cargas-proveedor.pesajes.autorizacion.store', [$weighing->carga_id, $weighing]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($operator)->post($route, [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => '9999',
            ])->assertSessionHasErrors('pin_autorizacion');
        }

        $response = $this->actingAs($operator)->post($route, [
            'administrador_id' => $administrator->id,
            'pin_autorizacion' => '0427',
        ]);

        $response->assertSessionHasErrors('pin_autorizacion');
        $this->assertStringContainsString(
            'Demasiados intentos.',
            session('errors')->first('pin_autorizacion'),
        );
    }

    private function administratorRole(): Rol
    {
        return Rol::factory()->create([
            'nombre' => 'ADMINISTRADOR',
            'activo' => true,
        ]);
    }

    private function administrator(
        Rol $role,
        ?string $pin,
        string $firstName = 'ADMINISTRADOR',
        bool $active = true,
    ): Usuario {
        $administrator = Usuario::factory()->create([
            'nombres' => $firstName,
            'pin_autorizacion_hash' => $pin,
            'activo' => $active,
        ]);
        $administrator->roles()->attach($role);

        return $administrator;
    }

    private function userWithLoadPermission(): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => 'CARGAS_REGISTRAR']);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
