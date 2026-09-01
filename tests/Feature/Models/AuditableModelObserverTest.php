<?php

namespace Tests\Feature\Models;

use App\Models\Cliente;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditableModelObserverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_create_records_actor_ip_and_changed_fields(): void
    {
        $actor = $this->userWithPermission('VENTAS_REGISTRAR');
        $documentType = TipoDocumento::factory()->create([
            'codigo' => 'DNI',
            'longitud_maxima' => 8,
        ]);

        $this->actingAs($actor)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.9'])
            ->post(route('clientes.store'), [
                'tipo_documento_id' => $documentType->id,
                'nro_documento' => '12345678',
                'nombres_razon_social' => 'Cliente auditado',
                'activo' => '1',
            ])
            ->assertRedirect(route('clientes.index'));

        $client = Cliente::query()->where('nro_documento', '12345678')->firstOrFail();

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $actor->id,
            'tabla_afectada' => 'clientes',
            'registro_id' => $client->id,
            'accion' => 'INSERT',
            'ip' => '127.0.0.9',
        ]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'nombres_razon_social',
            'valor_anterior' => null,
            'valor_nuevo' => 'Cliente auditado',
        ]);
    }

    public function test_authenticated_update_records_only_actual_changes(): void
    {
        $actor = $this->userWithPermission('VENTAS_REGISTRAR');
        $client = Cliente::factory()->create([
            'nombres_razon_social' => 'Nombre anterior',
            'telefono' => '111111111',
        ]);

        $this->actingAs($actor)->put(route('clientes.update', $client), [
            'tipo_documento_id' => null,
            'nro_documento' => null,
            'nombres_razon_social' => 'Nombre nuevo',
            'telefono' => '111111111',
            'direccion' => $client->direccion,
            'observacion' => $client->observacion,
            'activo' => '1',
        ])->assertRedirect(route('clientes.index'));

        $auditId = (int) DB::table('auditorias')
            ->where('tabla_afectada', 'clientes')
            ->where('registro_id', $client->id)
            ->where('accion', 'UPDATE')
            ->value('id');

        $this->assertGreaterThan(0, $auditId);
        $this->assertDatabaseHas('auditoria_detalles', [
            'auditoria_id' => $auditId,
            'campo' => 'nombres_razon_social',
            'valor_anterior' => 'Nombre anterior',
            'valor_nuevo' => 'Nombre nuevo',
        ]);
        $this->assertDatabaseMissing('auditoria_detalles', [
            'auditoria_id' => $auditId,
            'campo' => 'telefono',
        ]);
    }

    public function test_model_changes_outside_an_http_operation_are_not_audited(): void
    {
        Cliente::factory()->create();

        $this->assertDatabaseCount('auditorias', 0);
    }

    public function test_annulment_is_classified_and_records_the_responsible_user(): void
    {
        $actor = $this->userWithPermission('VENTAS_ANULAR');
        $sale = Venta::factory()->conTotal()->create(['usuario_id' => $actor->id]);

        $this->actingAs($actor)->post(route('ventas.anulacion.store', $sale), [
            'motivo_anulacion' => 'Error confirmado en el registro de la venta.',
        ])->assertRedirect(route('ventas.show', $sale));

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $actor->id,
            'tabla_afectada' => 'ventas',
            'registro_id' => $sale->id,
            'accion' => 'ANULAR',
        ]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'motivo_anulacion',
            'valor_nuevo' => 'Error confirmado en el registro de la venta.',
        ]);
    }

    private function userWithPermission(string $code): Usuario
    {
        $user = Usuario::factory()->create();
        $role = Rol::factory()->create([
            'nombre' => $code === 'VENTAS_ANULAR' ? 'ADMINISTRADOR OPERATIVO' : "ROL $code",
        ]);
        $permission = Permiso::factory()->create(['codigo' => $code]);

        $role->permisos()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
