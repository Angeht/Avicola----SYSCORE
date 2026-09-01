<?php

namespace App\Http\Requests;

use App\Models\ProcesoBeneficiado;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class AnularProcesoBeneficiadoRequest extends FormRequest
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
            'motivo_anulacion' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $process = $this->route('procesoBeneficiado');

                if (! $process instanceof ProcesoBeneficiado) {
                    return;
                }

                if ($process->estaAnulado()) {
                    $validator->errors()->add('motivo_anulacion', 'Este proceso de beneficiado ya fue anulado.');

                    return;
                }

                $destinationStock = DB::table('vw_saldo_mercaderia_actual')
                    ->where('producto_id', $process->producto_destino_id)
                    ->first();

                if ($destinationStock !== null
                    && $this->kilogramsToGrams($process->peso_resultante_kg) > $this->kilogramsToGrams($destinationStock->kg_disponibles)) {
                    $validator->errors()->add(
                        'motivo_anulacion',
                        'No puedes anular el proceso porque parte del producto beneficiado ya fue utilizado.',
                    );
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
            'motivo_anulacion.required' => 'Explica el motivo de la anulación.',
            'motivo_anulacion.string' => 'El motivo debe ser un texto válido.',
            'motivo_anulacion.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo_anulacion.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo_anulacion' => $this->filled('motivo_anulacion') && is_scalar($this->input('motivo_anulacion'))
                ? Str::of((string) $this->input('motivo_anulacion'))->squish()->toString()
                : null,
        ]);
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
