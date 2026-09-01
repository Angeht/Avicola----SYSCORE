<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCargaProveedorRequest extends FormRequest
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
            'proveedor_id' => [
                'required',
                Rule::exists('proveedores', 'id')->where('activo', true),
            ],
            'producto_id' => [
                'required',
                Rule::exists('productos', 'id')->where('activo', true),
            ],
            'fecha_carga' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'costo_kg' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001', 'max:99999999.9999'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Selecciona el proveedor.',
            'proveedor_id.exists' => 'El proveedor seleccionado no está disponible.',
            'producto_id.required' => 'Selecciona el producto recibido.',
            'producto_id.exists' => 'El producto seleccionado no está disponible.',
            'fecha_carga.required' => 'Indica la fecha de recepción.',
            'fecha_carga.date_format' => 'La fecha de recepción no es válida.',
            'fecha_carga.before_or_equal' => 'La fecha de recepción no puede ser futura.',
            'costo_kg.required' => 'Ingresa el costo por kilogramo.',
            'costo_kg.numeric' => 'El costo por kilogramo debe ser un número válido.',
            'costo_kg.decimal' => 'El costo por kilogramo puede tener hasta cuatro decimales.',
            'costo_kg.min' => 'El costo por kilogramo debe ser mayor que cero.',
            'costo_kg.max' => 'El costo por kilogramo supera el máximo permitido.',
            'observacion.max' => 'La observación general no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'proveedor_id' => $this->filled('proveedor_id') ? $this->integer('proveedor_id') : null,
            'producto_id' => $this->filled('producto_id') ? $this->integer('producto_id') : null,
            'costo_kg' => $this->normalizeDecimal($this->input('costo_kg')),
            'observacion' => $this->normalizeText($this->input('observacion')),
        ]);
    }

    private function normalizeDecimal(mixed $value): string
    {
        return Str::replace(',', '.', trim((string) $value));
    }

    private function normalizeText(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Str::of((string) $value)->squish()->toString();
    }
}
