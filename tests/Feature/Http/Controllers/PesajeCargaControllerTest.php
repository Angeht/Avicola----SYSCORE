<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CargaProveedor;
use App\Models\PagoProveedor;
use App\Models\Permiso;
use App\Models\PesajeCarga;
use App\Models\Rol;
use App\Models\TipoJaba;
use App\Models\Usuario;
use DOMDocument;
use DOMElement;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PesajeCargaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $load = CargaProveedor::factory()->create(['costo_total' => 0]);

        $this->get(route('cargas-proveedor.pesajes.create', $load))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_load_permission_is_forbidden(): void
    {
        $load = CargaProveedor::factory()->create(['costo_total' => 0]);

        $this->actingAs(Usuario::factory()->create())
            ->get(route('cargas-proveedor.pesajes.create', $load))
            ->assertForbidden();
    }

    public function test_form_lists_active_crate_types_and_allows_automatic_editable_tare_and_weighing_removal(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_kg' => 7.25, 'costo_total' => 0]);
        $activeCrateType = TipoJaba::factory()->create([
            'nombre' => 'JABA SELECCIONABLE',
            'tara_referencial_kg' => 6.800,
        ]);
        TipoJaba::factory()->inactivo()->create(['nombre' => 'JABA INACTIVA']);

        $response = $this->actingAs($user)->get(route('cargas-proveedor.pesajes.create', $load));
        $previousInternalErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);
        $crateTypeSelect = $document->getElementById('pesajes-0-tipo-jaba');
        $tareInput = $document->getElementById('pesajes-0-tara');
        $totalTareOutput = $document->getElementById('pesajes-0-tara-total');
        $removeButton = $document->getElementById('pesajes-0-eliminar');
        $activeCrateTypeOption = null;

        foreach ($crateTypeSelect?->getElementsByTagName('option') ?? [] as $option) {
            if ($option instanceof DOMElement && $option->getAttribute('value') === (string) $activeCrateType->getKey()) {
                $activeCrateTypeOption = $option;

                break;
            }
        }

        $response
            ->assertOk()
            ->assertSee($load->numero_carga)
            ->assertSee('JABA SELECCIONABLE')
            ->assertDontSee('JABA INACTIVA');
        $this->assertInstanceOf(DOMElement::class, $crateTypeSelect);
        $this->assertInstanceOf(DOMElement::class, $tareInput);
        $this->assertInstanceOf(DOMElement::class, $totalTareOutput);
        $this->assertInstanceOf(DOMElement::class, $removeButton);
        $this->assertInstanceOf(DOMElement::class, $activeCrateTypeOption);
        $this->assertFalse($crateTypeSelect->hasAttribute('disabled'));
        $this->assertFalse($tareInput->hasAttribute('disabled'));
        $this->assertFalse($removeButton->hasAttribute('disabled'));
        $this->assertSame('6.800', $activeCrateTypeOption->getAttribute('data-reference-tare'));
        $this->assertFalse($activeCrateTypeOption->hasAttribute('data-tare'));
        $this->assertTrue($tareInput->hasAttribute('data-tare'));
    }

    public function test_valid_weighings_are_saved_and_total_cost_is_calculated_from_net_weight(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_kg' => 7.25, 'costo_total' => 0]);
        $crateType = TipoJaba::factory()->create(['tara_referencial_kg' => 2.100]);

        $response = $this->actingAs($user)->post(
            route('cargas-proveedor.pesajes.store', $load),
            $this->validPayload($crateType),
        );

        $response
            ->assertRedirect(route('cargas-proveedor.show', $load))
            ->assertSessionHas('status', '2 pesaje(s) registrado(s). El costo total fue actualizado.');
        $this->assertDatabaseHas('pesajes_carga', [
            'carga_id' => $load->id,
            'tipo_jaba_id' => $crateType->id,
            'cantidad_jabas' => 10,
            'cantidad_pollos' => 100,
            'peso_bruto_kg' => 250.000,
            'tara_unitaria_aplicada_kg' => 2.200,
            'observacion' => 'lote principal',
        ]);
        $this->assertDatabaseHas('pesajes_carga', [
            'carga_id' => $load->id,
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => 50,
            'peso_bruto_kg' => 100.000,
            'tara_unitaria_aplicada_kg' => 0,
        ]);
        $this->assertDatabaseCount('pesajes_carga', 2);
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'costo_kg' => 7.2500,
            'costo_total' => 2378.00,
        ]);
    }

    public function test_new_weighings_recalculate_cost_using_all_accumulated_net_weight(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_kg' => 7.25, 'costo_total' => 725]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'peso_bruto_kg' => 100,
        ]);

        $response = $this->actingAs($user)->post(route('cargas-proveedor.pesajes.store', $load), [
            'pesajes' => [[
                'tipo_jaba_id' => null,
                'cantidad_jabas' => 0,
                'cantidad_pollos' => 25,
                'peso_bruto_kg' => '50.000',
                'tara_unitaria_aplicada_kg' => '99.000',
                'observacion' => null,
            ]],
        ]);

        $response->assertRedirect(route('cargas-proveedor.show', $load));
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'costo_total' => 1087.50,
        ]);
        $this->assertDatabaseCount('pesajes_carga', 2);
    }

    public function test_weighing_with_crates_requires_type_positive_tare_and_positive_net_weight(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_total' => 0]);

        $response = $this->actingAs($user)
            ->from(route('cargas-proveedor.pesajes.create', $load))
            ->post(route('cargas-proveedor.pesajes.store', $load), [
                'pesajes' => [[
                    'tipo_jaba_id' => null,
                    'cantidad_jabas' => 5,
                    'cantidad_pollos' => 50,
                    'peso_bruto_kg' => '0.000',
                    'tara_unitaria_aplicada_kg' => '0',
                    'observacion' => null,
                ]],
            ]);

        $response->assertSessionHasErrors([
            'pesajes.0.tipo_jaba_id' => 'Selecciona el tipo de jaba para este pesaje.',
            'pesajes.0.tara_unitaria_aplicada_kg' => 'La tara aplicada debe ser mayor que cero cuando hay jabas.',
            'pesajes.0.peso_bruto_kg',
        ]);
        $this->assertDatabaseCount('pesajes_carga', 0);
    }

    public function test_gross_weight_must_exceed_total_tare(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_total' => 0]);
        $crateType = TipoJaba::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('cargas-proveedor.pesajes.create', $load))
            ->post(route('cargas-proveedor.pesajes.store', $load), [
                'pesajes' => [[
                    'tipo_jaba_id' => $crateType->id,
                    'cantidad_jabas' => 10,
                    'cantidad_pollos' => 50,
                    'peso_bruto_kg' => '20.000',
                    'tara_unitaria_aplicada_kg' => '2.000',
                    'observacion' => null,
                ]],
            ]);

        $response->assertSessionHasErrors([
            'pesajes.0.peso_bruto_kg' => 'El peso bruto debe ser mayor que la tara total del pesaje.',
        ]);
        $this->assertDatabaseCount('pesajes_carga', 0);
    }

    public function test_inactive_crate_type_and_unexpected_weighing_key_are_rejected(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_total' => 0]);
        $crateType = TipoJaba::factory()->inactivo()->create();
        $payload = $this->validPayload($crateType);
        $payload['pesajes'][0]['carga_id'] = 999;

        $response = $this->actingAs($user)
            ->from(route('cargas-proveedor.pesajes.create', $load))
            ->post(route('cargas-proveedor.pesajes.store', $load), $payload);

        $response->assertSessionHasErrors([
            'pesajes.0' => 'Uno de los pesajes contiene datos no permitidos.',
            'pesajes.0.tipo_jaba_id' => 'El tipo de jaba seleccionado no está disponible.',
        ]);
        $this->assertDatabaseCount('pesajes_carga', 0);
    }

    public function test_cancelled_load_rejects_new_weighings(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->anulada()->create(['costo_total' => 0]);

        $this->actingAs($user)
            ->post(route('cargas-proveedor.pesajes.store', $load), [
                'pesajes' => [[
                    'tipo_jaba_id' => null,
                    'cantidad_jabas' => 0,
                    'cantidad_pollos' => 25,
                    'peso_bruto_kg' => '50.000',
                    'tara_unitaria_aplicada_kg' => '0',
                    'observacion' => null,
                ]],
            ])
            ->assertSessionHasErrors([
                'pesajes' => 'No se pueden agregar pesajes a una carga anulada.',
            ]);

        $this->assertDatabaseCount('pesajes_carga', 0);
        $this->assertSame('0.00', $load->fresh()->costo_total);
    }

    public function test_load_with_active_payment_rejects_new_weighings(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create(['costo_kg' => 5, 'costo_total' => 500]);
        PagoProveedor::factory()->create(['carga_id' => $load->id, 'monto' => 100]);

        $this->actingAs($user)
            ->post(route('cargas-proveedor.pesajes.store', $load), [
                'pesajes' => [[
                    'tipo_jaba_id' => null,
                    'cantidad_jabas' => 0,
                    'cantidad_pollos' => 25,
                    'peso_bruto_kg' => '50.000',
                    'tara_unitaria_aplicada_kg' => '0',
                    'observacion' => null,
                ]],
            ])
            ->assertSessionHasErrors([
                'pesajes' => 'No se pueden agregar pesajes porque la carga ya tiene pagos vigentes.',
            ]);

        $this->assertDatabaseCount('pesajes_carga', 0);
        $this->assertSame('500.00', $load->fresh()->costo_total);
    }

    public function test_load_detail_offers_an_authorized_edit_action_for_each_weighing(): void
    {
        $user = $this->userWithPermission();
        $load = CargaProveedor::factory()->create([
            'costo_kg' => 5,
            'costo_total' => 500,
        ]);
        $firstWeighing = PesajeCarga::factory()->create(['carga_id' => $load->id]);
        $secondWeighing = PesajeCarga::factory()->sinJabas()->create(['carga_id' => $load->id]);
        PagoProveedor::factory()->create([
            'carga_id' => $load->id,
            'monto' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('cargas-proveedor.show', $load))
            ->assertOk()
            ->assertSee(route('cargas-proveedor.pesajes.autorizacion.create', [$load, $firstWeighing]))
            ->assertSee(route('cargas-proveedor.pesajes.autorizacion.create', [$load, $secondWeighing]));
    }

    public function test_authorized_update_changes_selected_weighing_recalculates_cost_and_consumes_authorization(): void
    {
        $operator = $this->userWithPermission();
        $administrator = $this->administrator('0427');
        $load = CargaProveedor::factory()->create(['costo_kg' => 5, 'costo_total' => 0]);
        $crateType = TipoJaba::factory()->create(['tara_referencial_kg' => 2]);
        $weighing = PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'cantidad_pollos' => 50,
            'peso_bruto_kg' => 100,
        ]);
        PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'peso_bruto_kg' => 50,
        ]);
        $this->authorizeEdit($operator, $administrator, $weighing, '0427');

        $response = $this->put(route('cargas-proveedor.pesajes.update', [$load, $weighing]), [
            'tipo_jaba_id' => $crateType->id,
            'cantidad_jabas' => 10,
            'cantidad_pollos' => 80,
            'peso_bruto_kg' => '120,000',
            'tara_unitaria_aplicada_kg' => '2,000',
            'observacion' => '  lote   corregido ',
        ]);

        $response
            ->assertRedirect(route('cargas-proveedor.show', $load))
            ->assertSessionHas('status', 'Pesaje actualizado con autorización administrativa. El costo total fue recalculado.');
        $this->assertDatabaseHas('pesajes_carga', [
            'id' => $weighing->id,
            'tipo_jaba_id' => $crateType->id,
            'cantidad_jabas' => 10,
            'cantidad_pollos' => 80,
            'peso_bruto_kg' => 120,
            'tara_unitaria_aplicada_kg' => 2,
            'observacion' => 'lote corregido',
            'editado_por' => $operator->id,
            'autorizado_por' => $administrator->id,
        ]);
        $this->assertNotNull($weighing->fresh()->editado_at);
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'costo_total' => 750,
        ]);
        $this->assertDatabaseHas('auditoria_detalles', [
            'campo' => 'autorizado_por',
            'valor_nuevo' => (string) $administrator->id,
        ]);

        $this->get(route('cargas-proveedor.pesajes.edit', [$load, $weighing]))
            ->assertRedirect(route('cargas-proveedor.pesajes.autorizacion.create', [$load, $weighing]))
            ->assertSessionHasErrors('autorizacion');
    }

    public function test_invalid_update_preserves_weighing_and_keeps_authorization_for_correction(): void
    {
        $operator = $this->userWithPermission();
        $administrator = $this->administrator('0427');
        $load = CargaProveedor::factory()->create(['costo_kg' => 5, 'costo_total' => 500]);
        $crateType = TipoJaba::factory()->create(['tara_referencial_kg' => 2]);
        $weighing = PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'peso_bruto_kg' => 100,
        ]);
        $this->authorizeEdit($operator, $administrator, $weighing, '0427');

        $response = $this->put(route('cargas-proveedor.pesajes.update', [$load, $weighing]), [
            'tipo_jaba_id' => $crateType->id,
            'cantidad_jabas' => 10,
            'cantidad_pollos' => 80,
            'peso_bruto_kg' => '20.000',
            'tara_unitaria_aplicada_kg' => '2.000',
            'observacion' => null,
        ]);

        $response->assertSessionHasErrors([
            'peso_bruto_kg' => 'El peso bruto debe ser mayor que la tara total del pesaje.',
        ]);
        $this->assertDatabaseHas('pesajes_carga', [
            'id' => $weighing->id,
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'peso_bruto_kg' => 100,
            'editado_por' => null,
            'autorizado_por' => null,
        ]);
        $this->get(route('cargas-proveedor.pesajes.edit', [$load, $weighing]))
            ->assertOk()
            ->assertSee('Guardar corrección');
    }

    public function test_paid_load_weighing_can_be_updated_when_corrected_cost_covers_active_payments(): void
    {
        $operator = $this->userWithPermission();
        $administrator = $this->administrator('0427');
        $load = CargaProveedor::factory()->create(['costo_kg' => 5, 'costo_total' => 500]);
        $weighing = PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'peso_bruto_kg' => 100,
        ]);
        $this->authorizeEdit($operator, $administrator, $weighing, '0427');
        PagoProveedor::factory()->create(['carga_id' => $load->id, 'monto' => 100]);

        $response = $this->put(route('cargas-proveedor.pesajes.update', [$load, $weighing]), [
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => 80,
            'peso_bruto_kg' => '120.000',
            'tara_unitaria_aplicada_kg' => '0',
            'observacion' => 'Corrección con pago',
        ]);

        $response
            ->assertRedirect(route('cargas-proveedor.show', $load))
            ->assertSessionHas('status', 'Pesaje actualizado con autorización administrativa. El costo total fue recalculado.');
        $this->assertDatabaseHas('pesajes_carga', [
            'id' => $weighing->id,
            'cantidad_pollos' => 80,
            'peso_bruto_kg' => 120,
            'observacion' => 'Corrección con pago',
            'editado_por' => $operator->id,
            'autorizado_por' => $administrator->id,
        ]);
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'costo_total' => 600,
        ]);
    }

    public function test_paid_load_weighing_rejects_a_corrected_cost_below_active_payments(): void
    {
        $operator = $this->userWithPermission();
        $administrator = $this->administrator('0427');
        $load = CargaProveedor::factory()->create(['costo_kg' => 5, 'costo_total' => 500]);
        $weighing = PesajeCarga::factory()->sinJabas()->create([
            'carga_id' => $load->id,
            'peso_bruto_kg' => 100,
        ]);
        $this->authorizeEdit($operator, $administrator, $weighing, '0427');
        PagoProveedor::factory()->create(['carga_id' => $load->id, 'monto' => 450]);

        $response = $this->put(route('cargas-proveedor.pesajes.update', [$load, $weighing]), [
            'tipo_jaba_id' => null,
            'cantidad_jabas' => 0,
            'cantidad_pollos' => 80,
            'peso_bruto_kg' => '80.000',
            'tara_unitaria_aplicada_kg' => '0',
            'observacion' => 'No debe persistir',
        ]);

        $response->assertSessionHasErrors([
            'pesaje' => 'El costo total corregido no puede ser menor que el total ya pagado (S/ 450.00).',
        ]);
        $this->assertDatabaseHas('pesajes_carga', [
            'id' => $weighing->id,
            'peso_bruto_kg' => 100,
            'editado_por' => null,
            'autorizado_por' => null,
        ]);
        $this->assertDatabaseHas('cargas_proveedor', [
            'id' => $load->id,
            'costo_total' => 500,
        ]);
        $this->get(route('cargas-proveedor.pesajes.edit', [$load, $weighing]))
            ->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(TipoJaba $crateType): array
    {
        return [
            'pesajes' => [
                [
                    'tipo_jaba_id' => $crateType->id,
                    'cantidad_jabas' => 10,
                    'cantidad_pollos' => 100,
                    'peso_bruto_kg' => '250,000',
                    'tara_unitaria_aplicada_kg' => '2,200',
                    'observacion' => '  lote   principal ',
                ],
                [
                    'tipo_jaba_id' => $crateType->id,
                    'cantidad_jabas' => 0,
                    'cantidad_pollos' => 50,
                    'peso_bruto_kg' => '100.000',
                    'tara_unitaria_aplicada_kg' => '99.000',
                    'observacion' => null,
                ],
            ],
        ];
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

    private function administrator(string $pin): Usuario
    {
        $administrator = Usuario::factory()->create([
            'pin_autorizacion_hash' => $pin,
        ]);
        $role = Rol::factory()->create([
            'nombre' => 'ADMINISTRADOR',
            'activo' => true,
        ]);
        $administrator->roles()->attach($role);

        return $administrator;
    }

    private function authorizeEdit(
        Usuario $operator,
        Usuario $administrator,
        PesajeCarga $weighing,
        string $pin,
    ): void {
        $this->actingAs($operator)
            ->post(route('cargas-proveedor.pesajes.autorizacion.store', [$weighing->carga_id, $weighing]), [
                'administrador_id' => $administrator->id,
                'pin_autorizacion' => $pin,
            ])
            ->assertRedirect(route('cargas-proveedor.pesajes.edit', [$weighing->carga_id, $weighing]));
    }
}
