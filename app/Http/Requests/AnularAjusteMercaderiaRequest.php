<?php

namespace App\Http\Requests;

use App\Models\AjusteMercaderia;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class AnularAjusteMercaderiaRequest extends FormRequest
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
                $adjustment = $this->route('ajusteMercaderia');

                if (! $adjustment instanceof AjusteMercaderia) {
                    return;
                }

                if ($adjustment->estaAnulado()) {
                    $validator->errors()->add('motivo_anulacion', 'Este ajuste ya fue anulado.');

                    return;
                }

                if (DB::table('conciliacion_ajuste')->where('ajuste_id', $adjustment->getKey())->exists()) {
                    $validator->errors()->add('motivo_anulacion', 'No puedes anular un ajuste vinculado a una conciliación de mercadería.');

                    return;
                }

                if ($adjustment->ajusteCliente()->exists() || $adjustment->ajusteProveedor()->exists()) {
                    $validator->errors()->add('motivo_anulacion', 'Anula la devolución desde la venta o la carga correspondiente.');

                    return;
                }

                $type = TipoAjusteMercaderia::query()->find($adjustment->tipo_ajuste_id);

                if ($type?->naturaleza !== 'ENTRADA') {
                    return;
                }

                $stock = DB::table('vw_saldo_mercaderia_actual')
                    ->where('producto_id', $adjustment->producto_id)
                    ->first();

                if ($stock === null) {
                    return;
                }

                if ($adjustment->cantidad_pollos > (int) $stock->pollos_disponibles
                    || $this->kilogramsToGrams($adjustment->peso_kg) > $this->kilogramsToGrams($stock->kg_disponibles)) {
                    $validator->errors()->add('motivo_anulacion', 'No puedes anular esta entrada porque la mercadería ya fue utilizada.');
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
            'motivo_anulacion' => $this->filled('motivo_anulacion')
                ? (is_scalar($this->input('motivo_anulacion'))
                    ? Str::of((string) $this->input('motivo_anulacion'))->squish()->toString()
                    : $this->input('motivo_anulacion'))
                : null,
        ]);
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
