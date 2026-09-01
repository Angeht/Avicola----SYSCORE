<?php

namespace App\Http\Requests;

use App\Models\CargaProveedor;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProcesoBeneficiadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('MERCADERIA_AJUSTAR');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'carga_proveedor_id' => [
                'required',
                'integer',
                Rule::exists('cargas_proveedor', 'id')->whereNull('anulada_at'),
            ],
            'producto_destino_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where('activo', true),
            ],
            'cantidad_pollos' => ['required', 'integer', 'min:1', 'max:2000000000'],
            'peso_origen_kg' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'peso_resultante_kg' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'lte:peso_origen_kg', 'max:999999999.999'],
            'observacion' => ['nullable', 'string', 'max:255'],
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

                $load = CargaProveedor::query()
                    ->with('producto:id,nombre,modalidad_venta,activo')
                    ->find($this->integer('carga_proveedor_id'));
                $destination = Producto::query()->find($this->integer('producto_destino_id'));

                if ($load === null || $load->estaAnulada() || ! $load->producto->activo
                    || $load->producto->modalidad_venta !== Producto::MODALIDAD_PESAJE_VIVO) {
                    $validator->errors()->add('carga_proveedor_id', 'Selecciona una carga vigente de producto vivo.');

                    return;
                }

                if ($destination === null || ! $destination->activo || ! $destination->seVendeSoloPorPeso()) {
                    $validator->errors()->add('producto_destino_id', 'Selecciona un producto activo que se venda solo por kilogramos.');

                    return;
                }

                $summary = DB::table('vw_resumen_carga')
                    ->where('carga_id', $load->getKey())
                    ->first();
                $processed = ProcesoBeneficiado::query()
                    ->vigentes()
                    ->where('carga_proveedor_id', $load->getKey())
                    ->selectRaw('COALESCE(SUM(cantidad_pollos), 0) AS pollos')
                    ->selectRaw('COALESCE(SUM(peso_origen_kg), 0) AS kilogramos')
                    ->first();
                $stock = DB::table('vw_saldo_mercaderia_actual')
                    ->where('producto_id', $load->producto_id)
                    ->first();

                if ($summary === null || $stock === null) {
                    $validator->errors()->add('carga_proveedor_id', 'La carga no tiene mercadería disponible para beneficiar.');

                    return;
                }

                $availableBirds = min(
                    max(0, (int) $summary->cantidad_pollos - (int) $processed->pollos),
                    max(0, (int) $stock->pollos_disponibles),
                );
                $availableWeightGrams = min(
                    max(0, $this->kilogramsToGrams($summary->peso_neto_kg) - $this->kilogramsToGrams($processed->kilogramos)),
                    max(0, $this->kilogramsToGrams($stock->kg_disponibles)),
                );

                if ($this->integer('cantidad_pollos') > $availableBirds) {
                    $validator->errors()->add('cantidad_pollos', 'La cantidad supera las aves disponibles de la carga y del stock vivo.');
                }

                if ($this->kilogramsToGrams($this->input('peso_origen_kg')) > $availableWeightGrams) {
                    $validator->errors()->add('peso_origen_kg', 'El peso supera los kilogramos disponibles de la carga y del stock vivo.');
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
            'carga_proveedor_id.required' => 'Selecciona la carga de proveedor que se beneficiará.',
            'carga_proveedor_id.integer' => 'La carga seleccionada no es válida.',
            'carga_proveedor_id.exists' => 'La carga seleccionada no está disponible.',
            'producto_destino_id.required' => 'Selecciona el producto beneficiado que ingresará al stock.',
            'producto_destino_id.integer' => 'El producto de destino no es válido.',
            'producto_destino_id.exists' => 'El producto de destino no está disponible.',
            'cantidad_pollos.required' => 'Ingresa la cantidad de aves enviadas a beneficiado.',
            'cantidad_pollos.integer' => 'La cantidad de aves debe ser un número entero.',
            'cantidad_pollos.min' => 'Debes beneficiar al menos un ave.',
            'cantidad_pollos.max' => 'La cantidad de aves supera el máximo permitido.',
            'peso_origen_kg.required' => 'Ingresa el peso vivo enviado al proceso.',
            'peso_origen_kg.numeric' => 'El peso vivo debe ser un número válido.',
            'peso_origen_kg.decimal' => 'El peso vivo puede tener hasta tres decimales.',
            'peso_origen_kg.gt' => 'El peso vivo debe ser mayor que cero.',
            'peso_origen_kg.max' => 'El peso vivo supera el máximo permitido.',
            'peso_resultante_kg.required' => 'Ingresa los kilogramos beneficiados obtenidos.',
            'peso_resultante_kg.numeric' => 'El peso beneficiado debe ser un número válido.',
            'peso_resultante_kg.decimal' => 'El peso beneficiado puede tener hasta tres decimales.',
            'peso_resultante_kg.gt' => 'El peso beneficiado debe ser mayor que cero.',
            'peso_resultante_kg.lte' => 'El peso beneficiado no puede superar el peso vivo procesado.',
            'peso_resultante_kg.max' => 'El peso beneficiado supera el máximo permitido.',
            'observacion.string' => 'La observación debe ser un texto válido.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'carga_proveedor_id' => $this->normalizeIdentifier($this->input('carga_proveedor_id')),
            'producto_destino_id' => $this->normalizeIdentifier($this->input('producto_destino_id')),
            'cantidad_pollos' => $this->normalizeInteger($this->input('cantidad_pollos')),
            'peso_origen_kg' => $this->normalizeDecimal($this->input('peso_origen_kg')),
            'peso_resultante_kg' => $this->normalizeDecimal($this->input('peso_resultante_kg')),
            'observacion' => $this->filled('observacion') && is_scalar($this->input('observacion'))
                ? Str::of((string) $this->input('observacion'))->squish()->toString()
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
