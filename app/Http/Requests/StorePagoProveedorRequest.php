<?php

namespace App\Http\Requests;

use App\Models\MedioPago;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePagoProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('PROVEEDORES_PAGAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proveedor_id' => [
                'required',
                Rule::exists('proveedores', 'id'),
            ],
            'medio_pago_id' => [
                'required',
                Rule::exists('medios_pago', 'id')->where('activo', true),
            ],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'aplicar_descuento' => ['sometimes', 'boolean'],
            'monto_descuento' => [
                Rule::requiredIf($this->boolean('aplicar_descuento')),
                Rule::excludeIf(! $this->boolean('aplicar_descuento')),
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:999999999999.99',
            ],
            'motivo_descuento' => [
                Rule::requiredIf($this->boolean('aplicar_descuento')),
                Rule::excludeIf(! $this->boolean('aplicar_descuento')),
                'string',
                'min:5',
                'max:255',
            ],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['proveedor_id', 'medio_pago_id', 'monto', 'monto_descuento', 'motivo_descuento'])) {
                    return;
                }

                $user = $this->user();

                if ($this->boolean('aplicar_descuento')
                    && (! $user instanceof Usuario || ! $user->tienePermiso('PROVEEDORES_AJUSTAR'))) {
                    $validator->errors()->add('aplicar_descuento', 'No tienes permiso para reconocer descuentos del proveedor.');

                    return;
                }

                $pendingBalance = DB::table('vw_saldos_carga_proveedor')
                    ->where('proveedor_id', $this->integer('proveedor_id'))
                    ->where('saldo_pendiente', '>', 0)
                    ->sum('saldo_pendiente');

                if ($this->moneyToCents($pendingBalance) <= 0) {
                    $validator->errors()->add('proveedor_id', 'El proveedor seleccionado no tiene deuda pendiente.');

                    return;
                }

                $coveredCents = $this->moneyToCents($this->input('monto'))
                    + $this->moneyToCents($this->input('monto_descuento'));

                if ($coveredCents > $this->moneyToCents($pendingBalance)) {
                    $validator->errors()->add(
                        $this->boolean('aplicar_descuento') ? 'monto_descuento' : 'monto',
                        $this->boolean('aplicar_descuento')
                            ? 'El pago y el descuento no pueden superar la deuda total del proveedor.'
                            : 'El pago no puede superar la deuda total del proveedor.',
                    );
                }

                $paymentMethod = MedioPago::query()->find($this->integer('medio_pago_id'));

                if ($paymentMethod?->es_efectivo !== true) {
                    return;
                }

                if (! $user instanceof Usuario) {
                    return;
                }

                $cashSession = SesionCaja::query()
                    ->where('usuario_id', $user->getKey())
                    ->where('fecha_operacion', today()->toDateString())
                    ->abiertas()
                    ->orderByDesc('id')
                    ->first();

                if ($cashSession === null) {
                    $validator->errors()->add('medio_pago_id', 'Abre una sesión de caja antes de registrar un pago en efectivo.');

                    return;
                }

                $expectedCash = DB::table('vw_resumen_caja_usuario')
                    ->where('sesion_caja_id', $cashSession->getKey())
                    ->value('efectivo_esperado');

                if ($this->moneyToCents($this->input('monto')) > $this->moneyToCents($expectedCash)) {
                    $validator->errors()->add('monto', 'La caja no tiene efectivo suficiente para registrar este pago.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Selecciona el proveedor que deseas pagar.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
            'medio_pago_id.required' => 'Selecciona el medio de pago.',
            'medio_pago_id.exists' => 'El medio de pago seleccionado no está disponible.',
            'monto.required' => 'Ingresa el monto del pago.',
            'monto.numeric' => 'El monto debe ser un número válido.',
            'monto.decimal' => 'El monto puede tener hasta dos decimales.',
            'monto.min' => 'El monto debe ser mayor que cero.',
            'monto.max' => 'El monto supera el máximo permitido.',
            'aplicar_descuento.boolean' => 'La opción de descuento no es válida.',
            'monto_descuento.required' => 'Ingresa el importe reconocido por el proveedor.',
            'monto_descuento.numeric' => 'El descuento debe ser un número válido.',
            'monto_descuento.decimal' => 'El descuento puede tener hasta dos decimales.',
            'monto_descuento.min' => 'El descuento debe ser mayor que cero.',
            'motivo_descuento.required' => 'Explica brevemente el motivo del descuento.',
            'motivo_descuento.min' => 'El motivo del descuento debe tener al menos 5 caracteres.',
            'motivo_descuento.max' => 'El motivo del descuento no puede superar los 255 caracteres.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'proveedor_id' => $this->filled('proveedor_id') ? $this->integer('proveedor_id') : null,
            'medio_pago_id' => $this->filled('medio_pago_id') ? $this->integer('medio_pago_id') : null,
            'monto' => Str::replace(',', '.', $this->string('monto')->trim()->toString()),
            'aplicar_descuento' => $this->boolean('aplicar_descuento'),
            'monto_descuento' => $this->filled('monto_descuento')
                ? Str::replace(',', '.', $this->string('monto_descuento')->trim()->toString())
                : null,
            'motivo_descuento' => $this->filled('motivo_descuento')
                ? $this->string('motivo_descuento')->squish()->toString()
                : null,
            'observacion' => $this->filled('observacion')
                ? $this->string('observacion')->squish()->toString()
                : null,
        ]);
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
