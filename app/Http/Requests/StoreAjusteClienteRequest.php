<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAjusteClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario && $user->tienePermiso('CLIENTES_AJUSTAR');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['DESCUENTO', 'DEVOLUCION'])],
            'nuevo_saldo' => [Rule::excludeIf($this->input('tipo') !== 'DESCUENTO'), 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'motivo' => ['required', 'string', 'min:10', 'max:255'],
            'producto_id' => [Rule::excludeIf($this->input('tipo') !== 'DEVOLUCION'), 'required', 'integer', Rule::exists('productos', 'id')],
            'cantidad_pollos' => [Rule::excludeIf($this->input('tipo') !== 'DEVOLUCION'), 'required', 'integer', 'min:0', 'max:999999999'],
            'peso_kg' => [Rule::excludeIf($this->input('tipo') !== 'DEVOLUCION'), 'required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tipo.required' => 'Selecciona si registrarás un descuento o una devolución.',
            'tipo.in' => 'El tipo de ajuste seleccionado no es válido.',
            'nuevo_saldo.required' => 'Ingresa el nuevo saldo que quedará pendiente.',
            'nuevo_saldo.numeric' => 'El nuevo saldo debe ser un número válido.',
            'nuevo_saldo.decimal' => 'El nuevo saldo puede tener hasta dos decimales.',
            'nuevo_saldo.min' => 'El nuevo saldo no puede ser negativo.',
            'motivo.required' => 'Explica el motivo del ajuste.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
            'producto_id.required' => 'Selecciona el producto devuelto.',
            'producto_id.exists' => 'El producto seleccionado no está disponible.',
            'cantidad_pollos.required' => 'Indica cuántas aves fueron devueltas.',
            'cantidad_pollos.integer' => 'La cantidad de aves debe ser un número entero.',
            'peso_kg.required' => 'Indica el peso devuelto.',
            'peso_kg.numeric' => 'El peso debe ser un número válido.',
            'peso_kg.decimal' => 'El peso puede tener hasta tres decimales.',
            'peso_kg.min' => 'El peso devuelto debe ser mayor que cero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo' => is_scalar($this->input('tipo')) ? Str::upper(trim((string) $this->input('tipo'))) : $this->input('tipo'),
            'nuevo_saldo' => $this->normalizeDecimal($this->input('nuevo_saldo')),
            'producto_id' => $this->filled('producto_id') ? $this->integer('producto_id') : null,
            'cantidad_pollos' => $this->filled('cantidad_pollos') ? $this->integer('cantidad_pollos') : 0,
            'peso_kg' => $this->normalizeDecimal($this->input('peso_kg', 0)),
            'motivo' => $this->filled('motivo') && is_scalar($this->input('motivo'))
                ? Str::of((string) $this->input('motivo'))->squish()->toString()
                : $this->input('motivo'),
        ]);
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        return is_scalar($value) ? Str::replace(',', '.', trim((string) $value)) : $value;
    }
}
