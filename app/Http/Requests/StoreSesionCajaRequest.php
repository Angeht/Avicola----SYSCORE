<?php

namespace App\Http\Requests;

use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreSesionCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('CAJA_ABRIR_CERRAR');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monto_apertura' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user instanceof Usuario || $validator->errors()->has('monto_apertura')) {
                    return;
                }

                if (SesionCaja::query()->where('usuario_id', $user->getKey())->abiertas()->exists()) {
                    $validator->errors()->add('monto_apertura', 'Ya tienes una sesión de caja abierta.');
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
            'monto_apertura.required' => 'Ingresa el efectivo inicial de la caja.',
            'monto_apertura.numeric' => 'El monto de apertura debe ser un número válido.',
            'monto_apertura.decimal' => 'El monto de apertura puede tener hasta dos decimales.',
            'monto_apertura.min' => 'El monto de apertura no puede ser negativo.',
            'monto_apertura.max' => 'El monto de apertura supera el máximo permitido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'monto_apertura' => Str::replace(',', '.', $this->string('monto_apertura')->trim()->toString()),
        ]);
    }
}
