<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;

class GuardarPinAutorizacionUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('usuario');

        return $actor instanceof Usuario
            && $target instanceof Usuario
            && $actor->tienePermiso('USUARIOS_GESTIONAR')
            && $actor->puedeAdministrarUsuario($target)
            && $target->esAdministrador();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pin_autorizacion' => ['required', 'digits:4', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'pin_autorizacion.required' => 'Ingresa el PIN administrativo.',
            'pin_autorizacion.digits' => 'El PIN administrativo debe tener exactamente 4 dígitos.',
            'pin_autorizacion.confirmed' => 'La confirmación del PIN administrativo no coincide.',
        ];
    }
}
