<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\ConfiguracionEmpresa;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TicketVentaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $sale = Venta::factory()->conTotal()->create();

        $this->get(route('ventas.ticket', $sale))->assertRedirect(route('login'));
    }

    public function test_user_without_sale_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();
        $sale = Venta::factory()->conTotal()->create();

        $this->actingAs($user)->get(route('ventas.ticket', $sale))->assertForbidden();
    }

    public function test_authorized_user_can_print_sale_ticket_with_company_and_totals(): void
    {
        $user = $this->userWithPermission('VENTAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'RUC',
            'longitud_maxima' => 11,
        ]);
        ConfiguracionEmpresa::factory()->create([
            'razon_social' => 'AVÍCOLA SAN MARTÍN SAC',
            'nombre_comercial' => 'AVÍCOLA SAN MARTÍN',
            'nro_documento' => '20123456789',
            'tipo_documento_id' => $documentType->id,
            'mensaje_ticket' => 'Gracias por elegirnos.',
        ]);
        $sale = Venta::factory()->conTotal(200)->create([
            'numero_venta' => 'VEN-20260829-000321',
            'observacion' => '<script>alert("x")</script>',
        ]);
        $sale->detalles()->firstOrFail()->precioVersion->precioDia->producto->update([
            'nombre' => 'POLLO BENEFICIADO',
        ]);

        $response = $this->actingAs($user)->get(route('ventas.ticket', $sale));

        $response
            ->assertOk()
            ->assertSee('AVÍCOLA SAN MARTÍN')
            ->assertSee('20123456789')
            ->assertSee('VEN-20260829-000321')
            ->assertSee('POLLO BENEFICIADO')
            ->assertSee('S/ 200,00')
            ->assertSee('Gracias por elegirnos.')
            ->assertSee('window.print()', false)
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_cancelled_sale_ticket_is_clearly_marked(): void
    {
        $user = $this->userWithPermission('VENTAS_ANULAR');
        $sale = Venta::factory()->anulada($user)->conTotal()->create();

        $this->actingAs($user)
            ->get(route('ventas.ticket', $sale))
            ->assertOk()
            ->assertSee('Anulado')
            ->assertSee('Venta anulada para la prueba.');
    }

    private function userWithPermission(string $code): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create();
        $permission = Permiso::factory()->create(['codigo' => $code]);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
