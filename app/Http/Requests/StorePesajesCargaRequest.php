<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePesajesCargaRequest extends FormRequest
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
            'pesajes' => ['required', 'array', 'min:1', 'max:50'],
            'pesajes.*' => ['required', 'array:tipo_jaba_id,cantidad_jabas,cantidad_pollos,peso_bruto_kg,tara_unitaria_aplicada_kg,observacion'],
            'pesajes.*.tipo_jaba_id' => [
                'nullable',
                Rule::exists('tipos_jaba', 'id')->where('activo', true),
            ],
            'pesajes.*.cantidad_jabas' => ['required', 'integer', 'min:0', 'max:99999'],
            'pesajes.*.cantidad_pollos' => ['required', 'integer', 'min:1', 'max:9999999'],
            'pesajes.*.peso_bruto_kg' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
            'pesajes.*.tara_unitaria_aplicada_kg' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:9999999.999'],
            'pesajes.*.observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $weighings = $this->input('pesajes');

                if (! is_array($weighings)) {
                    return;
                }

                foreach ($weighings as $index => $weighing) {
                    if (! is_array($weighing)) {
                        continue;
                    }

                    $crates = (int) ($weighing['cantidad_jabas'] ?? 0);
                    $crateTypeId = $weighing['tipo_jaba_id'] ?? null;
                    $appliedTare = (float) ($weighing['tara_unitaria_aplicada_kg'] ?? 0);

                    if ($crates > 0 && blank($crateTypeId)) {
                        $validator->errors()->add(
                            "pesajes.$index.tipo_jaba_id",
                            'Selecciona el tipo de jaba para este pesaje.',
                        );
                    }

                    if ($crates > 0 && $appliedTare <= 0) {
                        $validator->errors()->add(
                            "pesajes.$index.tara_unitaria_aplicada_kg",
                            'La tara aplicada debe ser mayor que cero cuando hay jabas.',
                        );
                    }

                    if ($validator->errors()->hasAny([
                        "pesajes.$index.cantidad_jabas",
                        "pesajes.$index.peso_bruto_kg",
                        "pesajes.$index.tara_unitaria_aplicada_kg",
                    ])) {
                        continue;
                    }

                    $grossWeightGrams = (int) round((float) ($weighing['peso_bruto_kg'] ?? 0) * 1000);
                    $totalTareGrams = $crates * (int) round($appliedTare * 1000);

                    if ($grossWeightGrams <= $totalTareGrams) {
                        $validator->errors()->add(
                            "pesajes.$index.peso_bruto_kg",
                            'El peso bruto debe ser mayor que la tara total del pesaje.',
                        );
                    }
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
            'pesajes.required' => 'Registra al menos un pesaje de entrada.',
            'pesajes.array' => 'Los pesajes enviados no son válidos.',
            'pesajes.min' => 'Registra al menos un pesaje de entrada.',
            'pesajes.max' => 'Puedes registrar hasta 50 pesajes por envío.',
            'pesajes.*.array' => 'Uno de los pesajes contiene datos no permitidos.',
            'pesajes.*.tipo_jaba_id.exists' => 'El tipo de jaba seleccionado no está disponible.',
            'pesajes.*.cantidad_jabas.required' => 'Indica la cantidad de jabas.',
            'pesajes.*.cantidad_jabas.integer' => 'La cantidad de jabas debe ser un número entero.',
            'pesajes.*.cantidad_jabas.min' => 'La cantidad de jabas no puede ser negativa.',
            'pesajes.*.cantidad_jabas.max' => 'La cantidad de jabas supera el máximo permitido.',
            'pesajes.*.cantidad_pollos.required' => 'Indica la cantidad de pollos.',
            'pesajes.*.cantidad_pollos.integer' => 'La cantidad de pollos debe ser un número entero.',
            'pesajes.*.cantidad_pollos.min' => 'Cada pesaje debe incluir al menos un pollo.',
            'pesajes.*.cantidad_pollos.max' => 'La cantidad de pollos supera el máximo permitido.',
            'pesajes.*.peso_bruto_kg.required' => 'Ingresa el peso bruto.',
            'pesajes.*.peso_bruto_kg.numeric' => 'El peso bruto debe ser un número válido.',
            'pesajes.*.peso_bruto_kg.decimal' => 'El peso bruto puede tener hasta tres decimales.',
            'pesajes.*.peso_bruto_kg.min' => 'El peso bruto debe ser mayor que cero.',
            'pesajes.*.peso_bruto_kg.max' => 'El peso bruto supera el máximo permitido.',
            'pesajes.*.tara_unitaria_aplicada_kg.numeric' => 'La tara aplicada debe ser un número válido.',
            'pesajes.*.tara_unitaria_aplicada_kg.decimal' => 'La tara aplicada puede tener hasta tres decimales.',
            'pesajes.*.tara_unitaria_aplicada_kg.min' => 'La tara aplicada no puede ser negativa.',
            'pesajes.*.tara_unitaria_aplicada_kg.max' => 'La tara aplicada supera el máximo permitido.',
            'pesajes.*.observacion.max' => 'La observación del pesaje no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $weighings = $this->input('pesajes');

        $this->merge([
            'pesajes' => is_array($weighings)
                ? array_map(fn (mixed $weighing): mixed => $this->normalizeWeighing($weighing), $weighings)
                : $weighings,
        ]);
    }

    private function normalizeWeighing(mixed $weighing): mixed
    {
        if (! is_array($weighing)) {
            return $weighing;
        }

        $crates = isset($weighing['cantidad_jabas']) ? (int) $weighing['cantidad_jabas'] : null;

        return [
            ...$weighing,
            'tipo_jaba_id' => $crates === 0
                ? null
                : (filled($weighing['tipo_jaba_id'] ?? null) ? (int) $weighing['tipo_jaba_id'] : null),
            'cantidad_jabas' => $crates,
            'cantidad_pollos' => isset($weighing['cantidad_pollos']) ? (int) $weighing['cantidad_pollos'] : null,
            'peso_bruto_kg' => $this->normalizeDecimal($weighing['peso_bruto_kg'] ?? null),
            'tara_unitaria_aplicada_kg' => $crates === 0
                ? '0'
                : $this->normalizeDecimal($weighing['tara_unitaria_aplicada_kg'] ?? null),
            'observacion' => $this->normalizeText($weighing['observacion'] ?? null),
        ];
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
