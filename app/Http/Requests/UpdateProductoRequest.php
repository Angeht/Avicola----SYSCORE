<?php

namespace App\Http\Requests;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tieneAlgunPermiso(['CARGAS_REGISTRAR', 'PRECIO_DIA_GESTIONAR', 'MERCADERIA_AJUSTAR']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('producto');
        $uniqueName = Rule::unique('productos', 'nombre');

        if ($product instanceof Producto) {
            $uniqueName->ignore($product);
        }

        return [
            'nombre' => ['required', 'string', 'max:100', $uniqueName],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'unidad_medida_id' => [
                'required',
                Rule::exists('unidades_medida', 'id')->where('activo', true),
            ],
            'modalidad_venta' => ['required', Rule::in(array_keys(Producto::modalidadesVenta()))],
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre del producto.',
            'nombre.max' => 'El nombre del producto no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe un producto con ese nombre.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
            'unidad_medida_id.required' => 'Selecciona la unidad de medida.',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no está disponible.',
            'modalidad_venta.required' => 'Selecciona cómo se registrarán las ventas del producto.',
            'modalidad_venta.in' => 'La modalidad de venta seleccionada no es válida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => Str::upper($this->string('nombre')->squish()->toString()),
            'descripcion' => $this->filled('descripcion') ? $this->string('descripcion')->squish()->toString() : null,
            'unidad_medida_id' => $this->filled('unidad_medida_id') ? $this->integer('unidad_medida_id') : null,
            'modalidad_venta' => Str::upper($this->string('modalidad_venta')->trim()->toString()),
            'activo' => $this->boolean('activo'),
        ]);
    }
}
