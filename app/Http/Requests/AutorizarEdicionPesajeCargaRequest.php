<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use App\Services\AutorizacionPinAdministrador;
use Illuminate\Foundation\Http\FormRequest;

class AutorizarEdicionPesajeCargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('CARGAS_REGISTRAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'administrador_id' => ['required', 'integer'],
            'pin_autorizacion' => ['required', 'digits:4'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'administrador_id.required' => 'Selecciona el administrador que autoriza la edición.',
            'administrador_id.integer' => 'El administrador seleccionado no es válido.',
            'pin_autorizacion.required' => 'Ingresa el PIN administrativo.',
            'pin_autorizacion.digits' => 'El PIN administrativo debe tener exactamente 4 dígitos.',
        ];
    }

    public function administradorAutorizador(): Usuario
    {
        return app(AutorizacionPinAdministrador::class)
            ->confirmar($this, 'edicion-pesaje-carga');
    }
}
