<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\ConfiguracionEmpresa;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TicketCobranzaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $collection = Cobranza::factory()->create();

        $this->get(route('cobranzas.ticket', $collection))->assertRedirect(route('login'));
    }

    public function test_user_without_collection_permission_is_forbidden(): void
    {
        $user = Usuario::factory()->create();
        $collection = Cobranza::factory()->create();

        $this->actingAs($user)->get(route('cobranzas.ticket', $collection))->assertForbidden();
    }

    public function test_authorized_user_can_print_collection_ticket_with_applications(): void
    {
        $user = $this->userWithPermission('COBRANZAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'RUC',
            'longitud_maxima' => 11,
        ]);
        $client = Cliente::factory()->create([
            'nombres_razon_social' => 'CLIENTE MAYORISTA',
            'nro_documento' => '10456789012',
            'tipo_documento_id' => $documentType->id,
        ]);
        ConfiguracionEmpresa::factory()->create([
            'nombre_comercial' => 'GRANJA EL SOL',
            'mensaje_ticket' => 'Conserve este comprobante.',
        ]);
        $sale = Venta::factory()->conTotal(200)->create([
            'numero_venta' => 'VEN-20260829-000111',
            'cliente_id' => $client->id,
        ]);
        $collection = Cobranza::factory()->create([
            'numero_cobranza' => 'COB-20260829-000222',
            'cliente_id' => $client->id,
            'usuario_id' => $user->id,
            'monto_total' => 80,
            'observacion' => '<script>alert("x")</script>',
        ]);
        $collection->aplicaciones()->create([
            'venta_id' => $sale->id,
            'monto_aplicado' => 80,
        ]);

        $response = $this->actingAs($user)->get(route('cobranzas.ticket', $collection));

        $response
            ->assertOk()
            ->assertSee('GRANJA EL SOL')
            ->assertSee('COB-20260829-000222')
            ->assertSee('CLIENTE MAYORISTA')
            ->assertSee('VEN-20260829-000111')
            ->assertSee('S/ 80,00')
            ->assertSee('Conserve este comprobante.')
            ->assertSee('window.print()', false)
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_cancelled_collection_ticket_is_clearly_marked(): void
    {
        $user = $this->userWithPermission('COBRANZAS_ANULAR');
        $collection = Cobranza::factory()->anulada($user)->create();

        $this->actingAs($user)
            ->get(route('cobranzas.ticket', $collection))
            ->assertOk()
            ->assertSee('Anulado')
            ->assertSee('Cobranza anulada para la prueba.');
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
