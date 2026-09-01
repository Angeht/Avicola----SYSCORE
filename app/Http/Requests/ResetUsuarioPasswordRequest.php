<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetUsuarioPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $target = $this->route('usuario');

        return $user instanceof Usuario
            && $target instanceof Usuario
            && $user->tienePermiso('USUARIOS_GESTIONAR')
            && $user->puedeAdministrarUsuario($target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}
