<?php

namespace App\Http\Requests;

use App\Models\ConfiguracionRespaldo;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfiguracionRespaldoRequest extends FormRequest
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
            'activo' => ['required', 'boolean'],
            'frecuencia' => [
                'required',
                Rule::in([
                    ConfiguracionRespaldo::FRECUENCIA_DIARIA,
                    ConfiguracionRespaldo::FRECUENCIA_SEMANAL,
                    ConfiguracionRespaldo::FRECUENCIA_MENSUAL,
                ]),
            ],
            'hora' => ['required', 'date_format:H:i'],
            'dia_semana' => ['nullable', 'integer', 'between:1,7'],
            'dia_mes' => ['nullable', 'integer', 'between:1,28'],
            'retencion_cantidad' => ['required', 'integer', 'between:1,365'],
            'verificar_automaticamente' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'frecuencia.required' => 'Selecciona una frecuencia para las copias automáticas.',
            'frecuencia.in' => 'La frecuencia seleccionada no es válida.',
            'hora.required' => 'Indica la hora de ejecución.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM.',
            'dia_semana.between' => 'El día de la semana debe estar entre lunes y domingo.',
            'dia_mes.between' => 'El día del mes debe estar entre 1 y 28.',
            'retencion_cantidad.required' => 'Indica cuántas copias automáticas deseas conservar.',
            'retencion_cantidad.between' => 'La retención debe estar entre 1 y 365 copias.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->boolean('activo'),
            'frecuencia' => $this->string('frecuencia')->upper()->toString(),
            'hora' => $this->string('hora')->trim()->toString(),
            'dia_semana' => $this->filled('dia_semana') ? $this->integer('dia_semana') : null,
            'dia_mes' => $this->filled('dia_mes') ? $this->integer('dia_mes') : null,
            'retencion_cantidad' => $this->integer('retencion_cantidad'),
            'verificar_automaticamente' => $this->boolean('verificar_automaticamente'),
        ]);
    }
}
