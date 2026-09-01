<?php

namespace App\Http\Requests;

use App\Models\MedioPago;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCobranzaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('COBRANZAS_REGISTRAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clientes', 'id')->where('activo', true),
            ],
            'medio_pago_id' => [
                'required',
                'integer',
                Rule::exists('medios_pago', 'id')->where('activo', true),
            ],
            'monto_total' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateCashSession($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente que realiza el pago.',
            'cliente_id.integer' => 'El cliente seleccionado no es válido.',
            'cliente_id.exists' => 'El cliente seleccionado no está disponible.',
            'medio_pago_id.required' => 'Selecciona el medio de pago.',
            'medio_pago_id.integer' => 'El medio de pago seleccionado no es válido.',
            'medio_pago_id.exists' => 'El medio de pago seleccionado no está disponible.',
            'monto_total.required' => 'Ingresa el monto recibido.',
            'monto_total.numeric' => 'El monto recibido debe ser un número válido.',
            'monto_total.decimal' => 'El monto recibido puede tener hasta dos decimales.',
            'monto_total.min' => 'El monto recibido debe ser mayor que cero.',
            'monto_total.max' => 'El monto recibido supera el máximo permitido.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
            'observacion.string' => 'La observación debe ser un texto válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cliente_id' => $this->normalizeIdentifier($this->input('cliente_id')),
            'medio_pago_id' => $this->normalizeIdentifier($this->input('medio_pago_id')),
            'monto_total' => $this->normalizeMoney($this->input('monto_total')),
            'observacion' => $this->filled('observacion')
                ? (is_scalar($this->input('observacion'))
                    ? Str::of((string) $this->input('observacion'))->squish()->toString()
                    : $this->input('observacion'))
                : null,
        ]);
    }

    private function validateCashSession(Validator $validator): void
    {
        $paymentMethod = MedioPago::query()->find($this->integer('medio_pago_id'));

        if ($paymentMethod?->es_efectivo !== true) {
            return;
        }

        $user = $this->user();

        if (! $user instanceof Usuario) {
            return;
        }

        $hasOpenCashSession = SesionCaja::query()
            ->where('usuario_id', $user->getKey())
            ->where('fecha_operacion', today()->toDateString())
            ->abiertas()
            ->exists();

        if (! $hasOpenCashSession) {
            $validator->errors()->add('medio_pago_id', 'Abre una sesión de caja antes de registrar una cobranza en efectivo.');
        }
    }

    private function normalizeIdentifier(mixed $value): mixed
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $value;
    }

    private function normalizeMoney(mixed $value): mixed
    {
        return is_scalar($value)
            ? Str::replace(',', '.', Str::of((string) $value)->trim()->toString())
            : $value;
    }
}
