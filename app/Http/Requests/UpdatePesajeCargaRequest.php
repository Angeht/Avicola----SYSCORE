<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePesajeCargaRequest extends FormRequest
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
            'tipo_jaba_id' => [
                'nullable',
                Rule::exists('tipos_jaba', 'id')->where('activo', true),
            ],
            'cantidad_jabas' => ['required', 'integer', 'min:0', 'max:99999'],
            'cantidad_pollos' => ['required', 'integer', 'min:1', 'max:9999999'],
            'peso_bruto_kg' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
            'tara_unitaria_aplicada_kg' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:9999999.999'],
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
                $crates = $this->integer('cantidad_jabas');
                $crateTypeId = $this->input('tipo_jaba_id');
                $appliedTare = (float) $this->input('tara_unitaria_aplicada_kg', 0);

                if ($crates > 0 && blank($crateTypeId)) {
                    $validator->errors()->add(
                        'tipo_jaba_id',
                        'Selecciona el tipo de jaba para este pesaje.',
                    );
                }

                if ($crates > 0 && $appliedTare <= 0) {
                    $validator->errors()->add(
                        'tara_unitaria_aplicada_kg',
                        'La tara aplicada debe ser mayor que cero cuando hay jabas.',
                    );
                }

                if ($validator->errors()->hasAny([
                    'cantidad_jabas',
                    'peso_bruto_kg',
                    'tara_unitaria_aplicada_kg',
                ])) {
                    return;
                }

                $grossWeightGrams = (int) round((float) $this->input('peso_bruto_kg', 0) * 1000);
                $totalTareGrams = $crates * (int) round($appliedTare * 1000);

                if ($grossWeightGrams <= $totalTareGrams) {
                    $validator->errors()->add(
                        'peso_bruto_kg',
                        'El peso bruto debe ser mayor que la tara total del pesaje.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tipo_jaba_id.exists' => 'El tipo de jaba seleccionado no está disponible.',
            'cantidad_jabas.required' => 'Indica la cantidad de jabas.',
            'cantidad_jabas.integer' => 'La cantidad de jabas debe ser un número entero.',
            'cantidad_jabas.min' => 'La cantidad de jabas no puede ser negativa.',
            'cantidad_jabas.max' => 'La cantidad de jabas supera el máximo permitido.',
            'cantidad_pollos.required' => 'Indica la cantidad de pollos.',
            'cantidad_pollos.integer' => 'La cantidad de pollos debe ser un número entero.',
            'cantidad_pollos.min' => 'Cada pesaje debe incluir al menos un pollo.',
            'cantidad_pollos.max' => 'La cantidad de pollos supera el máximo permitido.',
            'peso_bruto_kg.required' => 'Ingresa el peso bruto.',
            'peso_bruto_kg.numeric' => 'El peso bruto debe ser un número válido.',
            'peso_bruto_kg.decimal' => 'El peso bruto puede tener hasta tres decimales.',
            'peso_bruto_kg.min' => 'El peso bruto debe ser mayor que cero.',
            'peso_bruto_kg.max' => 'El peso bruto supera el máximo permitido.',
            'tara_unitaria_aplicada_kg.numeric' => 'La tara aplicada debe ser un número válido.',
            'tara_unitaria_aplicada_kg.decimal' => 'La tara aplicada puede tener hasta tres decimales.',
            'tara_unitaria_aplicada_kg.min' => 'La tara aplicada no puede ser negativa.',
            'tara_unitaria_aplicada_kg.max' => 'La tara aplicada supera el máximo permitido.',
            'observacion.max' => 'La observación del pesaje no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $crates = $this->input('cantidad_jabas');
        $normalizedCrates = is_numeric($crates) ? (int) $crates : $crates;

        $this->merge([
            'tipo_jaba_id' => $normalizedCrates === 0
                ? null
                : (filled($this->input('tipo_jaba_id')) ? $this->integer('tipo_jaba_id') : null),
            'cantidad_jabas' => $normalizedCrates,
            'cantidad_pollos' => is_numeric($this->input('cantidad_pollos'))
                ? $this->integer('cantidad_pollos')
                : $this->input('cantidad_pollos'),
            'peso_bruto_kg' => $this->normalizeDecimal($this->input('peso_bruto_kg')),
            'tara_unitaria_aplicada_kg' => $normalizedCrates === 0
                ? '0'
                : $this->normalizeDecimal($this->input('tara_unitaria_aplicada_kg')),
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
