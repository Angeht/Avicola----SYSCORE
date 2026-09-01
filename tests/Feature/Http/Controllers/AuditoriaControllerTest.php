<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Auditoria;
use App\Models\AuditoriaDetalle;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuditoriaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('auditorias.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_audit_permission_is_forbidden(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->get(route('auditorias.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_filter_audits_and_values_are_escaped(): void
    {
        $actor = $this->userWithPermission('AUDITORIA_VER');
        $dangerousTable = 'ventas<script>alert(1)</script>';
        $visible = Auditoria::factory()->for($actor, 'usuario')->create([
            'tabla_afectada' => $dangerousTable,
            'accion' => 'ANULAR',
            'created_at' => now(),
        ]);
        $hidden = Auditoria::factory()->for($actor, 'usuario')->create([
            'tabla_afectada' => 'productos',
            'accion' => 'INSERT',
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($actor)->get(route('auditorias.index', [
            'accion' => 'ANULAR',
            'buscar' => 'ventas',
            'desde' => today()->toDateString(),
            'hasta' => today()->toDateString(),
        ]));

        $response
            ->assertOk()
            ->assertSee($dangerousTable)
            ->assertDontSee($dangerousTable, false)
            ->assertSee(route('auditorias.show', $visible))
            ->assertDontSee(route('auditorias.show', $hidden));
    }

    public function test_authorized_user_can_inspect_actor_ip_and_changed_values(): void
    {
        $actor = $this->userWithPermission('AUDITORIA_VER');
        $audit = Auditoria::factory()->for($actor, 'usuario')->create([
            'tabla_afectada' => 'ventas',
            'registro_id' => 42,
            'accion' => 'UPDATE',
            'ip' => '127.0.0.7',
        ]);
        AuditoriaDetalle::factory()->for($audit, 'auditoria')->create([
            'campo' => 'observacion',
            'valor_anterior' => 'Antes',
            'valor_nuevo' => 'Después',
        ]);

        $this->actingAs($actor)
            ->get(route('auditorias.show', $audit))
            ->assertOk()
            ->assertSee($actor->nombreCompleto())
            ->assertSee('127.0.0.7')
            ->assertSee('Antes')
            ->assertSee('Después');
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
