<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreConciliacionMercaderiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('MERCADERIA_CONCILIAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where('activo', true),
            ],
            'tipo_conciliacion' => ['required', Rule::in(['CIERRE', 'EXTRAORDINARIA'])],
            'cantidad_pollos_fisico' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'peso_fisico_kg' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'observacion' => [
                Rule::requiredIf($this->input('tipo_conciliacion') === 'EXTRAORDINARIA'),
                'nullable',
                'string',
                'min:10',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'producto_id.required' => 'Selecciona el producto que deseas conciliar.',
            'producto_id.integer' => 'El producto seleccionado no es válido.',
            'producto_id.exists' => 'El producto seleccionado no está disponible.',
            'tipo_conciliacion.required' => 'Selecciona el tipo de conciliación.',
            'tipo_conciliacion.in' => 'El tipo de conciliación no es válido.',
            'cantidad_pollos_fisico.required' => 'Ingresa la cantidad física de aves.',
            'cantidad_pollos_fisico.integer' => 'La cantidad física de aves debe ser un número entero.',
            'cantidad_pollos_fisico.min' => 'La cantidad física de aves no puede ser negativa.',
            'cantidad_pollos_fisico.max' => 'La cantidad física de aves supera el máximo permitido.',
            'peso_fisico_kg.required' => 'Ingresa el peso físico encontrado.',
            'peso_fisico_kg.numeric' => 'El peso físico debe ser un número válido.',
            'peso_fisico_kg.decimal' => 'El peso físico puede tener hasta tres decimales.',
            'peso_fisico_kg.min' => 'El peso físico no puede ser negativo.',
            'peso_fisico_kg.max' => 'El peso físico supera el máximo permitido.',
            'observacion.required' => 'Explica el motivo de la conciliación extraordinaria.',
            'observacion.string' => 'La observación debe ser un texto válido.',
            'observacion.min' => 'La observación debe tener al menos 10 caracteres.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('tipo_conciliacion');

        $this->merge([
            'producto_id' => $this->normalizeIdentifier($this->input('producto_id')),
            'tipo_conciliacion' => is_scalar($type)
                ? Str::upper(Str::of((string) $type)->trim()->toString())
                : $type,
            'cantidad_pollos_fisico' => $this->normalizeInteger($this->input('cantidad_pollos_fisico')),
            'peso_fisico_kg' => $this->normalizeDecimal($this->input('peso_fisico_kg')),
            'observacion' => $this->filled('observacion')
                ? (is_scalar($this->input('observacion'))
                    ? Str::of((string) $this->input('observacion'))->squish()->toString()
                    : $this->input('observacion'))
                : null,
        ]);
    }

    private function normalizeIdentifier(mixed $value): mixed
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $value;
    }

    private function normalizeInteger(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $value = Str::of((string) $value)->trim()->toString();

        return $value === '' ? 0 : $value;
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $value = Str::replace(',', '.', Str::of((string) $value)->trim()->toString());

        return $value === '' ? 0 : $value;
    }
}
