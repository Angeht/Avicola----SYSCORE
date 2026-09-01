<?php

namespace App\Http\Requests;

use App\Models\Cobranza;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class AnularCobranzaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('COBRANZAS_ANULAR');
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
                $collection = $this->route('cobranza');

                if (! $collection instanceof Cobranza) {
                    return;
                }

                if ($collection->estaAnulada()) {
                    $validator->errors()->add('motivo_anulacion', 'Esta cobranza ya fue anulada.');

                    return;
                }

                if ($collection->sesion_caja_id === null) {
                    return;
                }

                $cashSessionIsClosed = SesionCaja::query()
                    ->whereKey($collection->sesion_caja_id)
                    ->whereNotNull('cierre_at')
                    ->exists();

                if ($cashSessionIsClosed) {
                    $validator->errors()->add(
                        'motivo_anulacion',
                        'No puedes anular una cobranza vinculada a una sesión de caja cerrada.',
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
            'motivo_anulacion.string' => 'El motivo debe ser un texto válido.',
            'motivo_anulacion.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo_anulacion.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo_anulacion' => $this->filled('motivo_anulacion')
                ? (is_scalar($this->input('motivo_anulacion'))
                    ? Str::of((string) $this->input('motivo_anulacion'))->squish()->toString()
                    : $this->input('motivo_anulacion'))
                : null,
        ]);
    }
}
