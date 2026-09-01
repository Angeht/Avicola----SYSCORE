<?php

namespace App\Http\Requests;

use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAjusteMercaderiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('MERCADERIA_AJUSTAR');
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
            'tipo_ajuste_id' => [
                'required',
                'integer',
                Rule::exists('tipos_ajuste_mercaderia', 'id')->where('activo', true),
            ],
            'cantidad_pollos' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'peso_kg' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'motivo' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $birds = $this->integer('cantidad_pollos');
                $weightGrams = $this->kilogramsToGrams($this->input('peso_kg'));

                if ($birds === 0 && $weightGrams === 0) {
                    $validator->errors()->add('cantidad_pollos', 'Ingresa al menos una cantidad de aves o un peso mayor que cero.');

                    return;
                }

                $adjustmentType = TipoAjusteMercaderia::query()->find($this->integer('tipo_ajuste_id'));

                if ($adjustmentType === null) {
                    return;
                }

                if ($adjustmentType->codigo === 'SALDO_INICIAL'
                    && DB::table('vw_movimientos_mercaderia')->where('producto_id', $this->integer('producto_id'))->exists()) {
                    $validator->errors()->add('tipo_ajuste_id', 'El saldo inicial solo puede registrarse antes del primer movimiento del producto.');

                    return;
                }

                if ($adjustmentType->naturaleza !== 'SALIDA') {
                    return;
                }

                $stock = DB::table('vw_saldo_mercaderia_actual')
                    ->where('producto_id', $this->integer('producto_id'))
                    ->first();

                if ($stock === null) {
                    return;
                }

                if ($birds > 0 && $birds > (int) $stock->pollos_disponibles) {
                    $validator->errors()->add('cantidad_pollos', 'La salida supera las aves disponibles del producto.');
                }

                if ($weightGrams > 0 && $weightGrams > $this->kilogramsToGrams($stock->kg_disponibles)) {
                    $validator->errors()->add('peso_kg', 'La salida supera el peso disponible del producto.');
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
            'producto_id.required' => 'Selecciona el producto que deseas ajustar.',
            'producto_id.integer' => 'El producto seleccionado no es válido.',
            'producto_id.exists' => 'El producto seleccionado no está disponible.',
            'tipo_ajuste_id.required' => 'Selecciona el tipo de ajuste.',
            'tipo_ajuste_id.integer' => 'El tipo de ajuste seleccionado no es válido.',
            'tipo_ajuste_id.exists' => 'El tipo de ajuste seleccionado no está disponible.',
            'cantidad_pollos.required' => 'Ingresa la cantidad de aves del ajuste.',
            'cantidad_pollos.integer' => 'La cantidad de aves debe ser un número entero.',
            'cantidad_pollos.min' => 'La cantidad de aves no puede ser negativa.',
            'cantidad_pollos.max' => 'La cantidad de aves supera el máximo permitido.',
            'peso_kg.required' => 'Ingresa el peso del ajuste.',
            'peso_kg.numeric' => 'El peso debe ser un número válido.',
            'peso_kg.decimal' => 'El peso puede tener hasta tres decimales.',
            'peso_kg.min' => 'El peso no puede ser negativo.',
            'peso_kg.max' => 'El peso supera el máximo permitido.',
            'motivo.required' => 'Explica el motivo del ajuste.',
            'motivo.string' => 'El motivo debe ser un texto válido.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'producto_id' => $this->normalizeIdentifier($this->input('producto_id')),
            'tipo_ajuste_id' => $this->normalizeIdentifier($this->input('tipo_ajuste_id')),
            'cantidad_pollos' => $this->normalizeInteger($this->input('cantidad_pollos')),
            'peso_kg' => $this->normalizeDecimal($this->input('peso_kg')),
            'motivo' => $this->filled('motivo')
                ? (is_scalar($this->input('motivo'))
                    ? Str::of((string) $this->input('motivo'))->squish()->toString()
                    : $this->input('motivo'))
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

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
