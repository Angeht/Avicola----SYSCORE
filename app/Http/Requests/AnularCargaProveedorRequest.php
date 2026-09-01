<?php

namespace App\Http\Requests;

use App\Models\CargaProveedor;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class AnularCargaProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('CARGAS_ANULAR');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo_anulacion' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $load = $this->route('cargaProveedor');

                if (! $load instanceof CargaProveedor) {
                    return;
                }

                if ($load->estaAnulada()) {
                    $validator->errors()->add('motivo_anulacion', 'Esta carga ya fue anulada.');

                    return;
                }

                if ($load->pagosProveedor()->whereNull('anulada_at')->exists()) {
                    $validator->errors()->add(
                        'motivo_anulacion',
                        'Anula primero los pagos vigentes de la carga.',
                    );

                    return;
                }

                if ($load->procesosBeneficiado()->whereNull('anulado_at')->exists()) {
                    $validator->errors()->add(
                        'motivo_anulacion',
                        'Anula primero los procesos de beneficiado vigentes de la carga.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
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
        $reason = $this->input('motivo_anulacion');

        $this->merge([
            'motivo_anulacion' => is_scalar($reason) && filled($reason)
                ? Str::of((string) $reason)->squish()->toString()
                : null,
        ]);
    }
}
