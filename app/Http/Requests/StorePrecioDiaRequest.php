<?php

namespace App\Http\Requests;

use App\Models\PrecioDia;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePrecioDiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('PRECIO_DIA_GESTIONAR');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'producto_id' => [
                'required',
                Rule::exists('productos', 'id')->where('activo', true),
            ],
            'fecha' => ['required', 'date_format:Y-m-d', 'date_equals:today'],
            'precio_kg' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001', 'max:99999999.9999'],
            'motivo_cambio' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['producto_id', 'fecha', 'precio_kg'])) {
                    return;
                }

                $priceDay = PrecioDia::query()
                    ->where('producto_id', $this->integer('producto_id'))
                    ->whereDate('fecha', $this->string('fecha')->toString())
                    ->first();
                $currentVersion = $priceDay?->versiones()
                    ->orderByDesc('vigente_desde')
                    ->orderByDesc('id')
                    ->first();

                if ($currentVersion === null) {
                    return;
                }

                if (blank($this->input('motivo_cambio'))) {
                    $validator->errors()->add('motivo_cambio', 'Explica el motivo del cambio de precio.');
                }

                if (sprintf('%.4F', (float) $currentVersion->precio_kg) === sprintf('%.4F', $this->float('precio_kg'))) {
                    $validator->errors()->add('precio_kg', 'El nuevo precio debe ser diferente al precio vigente.');
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
            'producto_id.required' => 'Selecciona el producto.',
            'producto_id.exists' => 'El producto seleccionado no está disponible.',
            'fecha.required' => 'Indica la fecha del precio.',
            'fecha.date_format' => 'La fecha del precio no es válida.',
            'fecha.date_equals' => 'Solo puedes registrar precios para la fecha actual.',
            'precio_kg.required' => 'Ingresa el precio por kilogramo.',
            'precio_kg.numeric' => 'El precio debe ser un número válido.',
            'precio_kg.decimal' => 'El precio puede tener hasta cuatro decimales.',
            'precio_kg.min' => 'El precio debe ser mayor que cero.',
            'precio_kg.max' => 'El precio supera el máximo permitido.',
            'motivo_cambio.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'producto_id' => $this->filled('producto_id') ? $this->integer('producto_id') : null,
            'precio_kg' => Str::replace(',', '.', $this->string('precio_kg')->trim()->toString()),
            'motivo_cambio' => $this->filled('motivo_cambio')
                ? $this->string('motivo_cambio')->squish()->toString()
                : null,
        ]);
    }
}
