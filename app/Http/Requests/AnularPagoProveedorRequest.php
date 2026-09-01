<?php

namespace App\Http\Requests;

use App\Models\PagoProveedor;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class AnularPagoProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('PROVEEDORES_PAGO_ANULAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo_anulacion' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $payment = $this->route('pagoProveedor');

                if (! $payment instanceof PagoProveedor) {
                    return;
                }

                if ($payment->estaAnulado()) {
                    $validator->errors()->add('motivo_anulacion', 'Este pago ya fue anulado.');

                    return;
                }

                if ($payment->sesion_caja_id === null) {
                    return;
                }

                $cashSessionIsClosed = SesionCaja::query()
                    ->whereKey($payment->sesion_caja_id)
                    ->whereNotNull('cierre_at')
                    ->exists();

                if ($cashSessionIsClosed) {
                    $validator->errors()->add(
                        'motivo_anulacion',
                        'No puedes anular un pago vinculado a una sesión de caja cerrada.',
                    );
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
            'motivo_anulacion.required' => 'Explica el motivo de la anulación.',
            'motivo_anulacion.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo_anulacion.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo_anulacion' => $this->filled('motivo_anulacion')
                ? Str::of($this->input('motivo_anulacion'))->squish()->toString()
                : null,
        ]);
    }
}
