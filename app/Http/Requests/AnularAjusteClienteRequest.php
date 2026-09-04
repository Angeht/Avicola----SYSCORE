<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AnularAjusteClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario && $user->tienePermiso('CLIENTES_AJUSTAR');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['motivo_anulacion' => ['required', 'string', 'min:10', 'max:255']];
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
        $this->merge([
            'motivo_anulacion' => $this->filled('motivo_anulacion') && is_scalar($this->input('motivo_anulacion'))
                ? Str::of((string) $this->input('motivo_anulacion'))->squish()->toString()
                : $this->input('motivo_anulacion'),
        ]);
    }
}
