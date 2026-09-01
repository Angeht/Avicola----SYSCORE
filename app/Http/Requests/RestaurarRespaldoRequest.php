<?php

namespace App\Http\Requests;

use App\Models\Respaldo;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RestaurarRespaldoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('RESPALDOS_GESTIONAR');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'confirmacion' => ['required', Rule::in(['RESTAURAR'])],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $backup = $this->route('respaldo');

                if ($backup instanceof Respaldo && ! $backup->estaRestaurable()) {
                    $validator->errors()->add('confirmacion', 'El respaldo debe estar disponible y verificado antes de restaurarlo.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Ingresa tu contraseña actual para autorizar la restauración.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'confirmacion.required' => 'Escribe RESTAURAR para confirmar la operación.',
            'confirmacion.in' => 'La confirmación debe ser exactamente RESTAURAR.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $confirmation = $this->input('confirmacion');

        $this->merge([
            'confirmacion' => is_scalar($confirmation)
                ? Str::of((string) $confirmation)->trim()->upper()->toString()
                : null,
        ]);
    }
}
