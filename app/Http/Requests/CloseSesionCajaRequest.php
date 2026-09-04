<?php

namespace App\Http\Requests;

use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class CloseSesionCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $cashSession = $this->route('sesionCaja');

        return $user instanceof Usuario
            && $cashSession instanceof SesionCaja
            && $user->tienePermiso('CAJA_ABRIR_CERRAR')
            && ($cashSession->usuario_id === $user->getKey() || $user->esAdministrador());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'administrador_id' => ['required', 'integer'],
            'pin_autorizacion' => ['required', 'digits:4'],
            'monto_contado_efectivo' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'observacion_cierre' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cashSession = $this->route('sesionCaja');

                if (! $cashSession instanceof SesionCaja || $validator->errors()->has('monto_contado_efectivo')) {
                    return;
                }

                if (! $cashSession->estaAbierta()) {
                    $validator->errors()->add('monto_contado_efectivo', 'Esta sesión de caja ya fue cerrada.');

                    return;
                }

                $expectedCash = (float) DB::table('vw_resumen_caja_usuario')
                    ->where('sesion_caja_id', $cashSession->getKey())
                    ->value('efectivo_esperado');
                $hasDifference = (int) round(($this->float('monto_contado_efectivo') - $expectedCash) * 100) !== 0;

                if ($hasDifference && blank($this->input('observacion_cierre'))) {
                    $validator->errors()->add('observacion_cierre', 'Explica la diferencia encontrada en el efectivo.');
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
            'administrador_id.required' => 'Selecciona el administrador que autoriza el cierre.',
            'administrador_id.integer' => 'El administrador seleccionado no es válido.',
            'pin_autorizacion.required' => 'Ingresa el PIN administrativo.',
            'pin_autorizacion.digits' => 'El PIN administrativo debe tener exactamente 4 dígitos.',
            'monto_contado_efectivo.required' => 'Ingresa el efectivo contado al cierre.',
            'monto_contado_efectivo.numeric' => 'El efectivo contado debe ser un número válido.',
            'monto_contado_efectivo.decimal' => 'El efectivo contado puede tener hasta dos decimales.',
            'monto_contado_efectivo.min' => 'El efectivo contado no puede ser negativo.',
            'monto_contado_efectivo.max' => 'El efectivo contado supera el máximo permitido.',
            'observacion_cierre.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'monto_contado_efectivo' => Str::replace(',', '.', $this->string('monto_contado_efectivo')->trim()->toString()),
            'observacion_cierre' => $this->filled('observacion_cierre')
                ? $this->string('observacion_cierre')->squish()->toString()
                : null,
        ]);
    }
}
