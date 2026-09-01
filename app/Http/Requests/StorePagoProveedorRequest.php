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
            'carga_id' => [
                'required',
                Rule::exists('cargas_proveedor', 'id')->whereNull('anulada_at'),
            ],
            'medio_pago_id' => [
                'required',
                Rule::exists('medios_pago', 'id')->where('activo', true),
            ],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
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
                if ($validator->errors()->hasAny(['carga_id', 'medio_pago_id', 'monto'])) {
                    return;
                }

                $balance = DB::table('vw_saldos_carga_proveedor')
                    ->where('carga_id', $this->integer('carga_id'))
                    ->first();

                if ($balance === null || $this->moneyToCents($balance->saldo_pendiente) <= 0) {
                    $validator->errors()->add('carga_id', 'La carga seleccionada no tiene saldo pendiente.');

                    return;
                }

                if ($this->moneyToCents($this->input('monto')) > $this->moneyToCents($balance->saldo_pendiente)) {
                    $validator->errors()->add('monto', 'El pago no puede superar el saldo pendiente de la carga.');
                }

                $paymentMethod = MedioPago::query()->find($this->integer('medio_pago_id'));

                if ($paymentMethod?->es_efectivo !== true) {
                    return;
                }

                $user = $this->user();

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
            'carga_id.required' => 'Selecciona la carga que deseas pagar.',
            'carga_id.exists' => 'La carga seleccionada no existe o está anulada.',
            'medio_pago_id.required' => 'Selecciona el medio de pago.',
            'medio_pago_id.exists' => 'El medio de pago seleccionado no está disponible.',
            'monto.required' => 'Ingresa el monto del pago.',
            'monto.numeric' => 'El monto debe ser un número válido.',
            'monto.decimal' => 'El monto puede tener hasta dos decimales.',
            'monto.min' => 'El monto debe ser mayor que cero.',
            'monto.max' => 'El monto supera el máximo permitido.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'carga_id' => $this->filled('carga_id') ? $this->integer('carga_id') : null,
            'medio_pago_id' => $this->filled('medio_pago_id') ? $this->integer('medio_pago_id') : null,
            'monto' => Str::replace(',', '.', $this->string('monto')->trim()->toString()),
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
