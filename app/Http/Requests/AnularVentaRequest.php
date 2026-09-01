<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class AnularVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->puedeEliminarVentas();
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
                $sale = $this->route('venta');

                if (! $sale instanceof Venta) {
                    return;
                }

                if ($sale->estaAnulada()) {
                    $validator->errors()->add('motivo_anulacion', 'Esta venta ya fue anulada.');

                    return;
                }

                $hasActiveCollections = DB::table('aplicacion_cobranzas as ac')
                    ->join('cobranzas as c', 'c.id', '=', 'ac.cobranza_id')
                    ->where('ac.venta_id', $sale->getKey())
                    ->whereNull('c.anulada_at')
                    ->exists();

                if ($hasActiveCollections) {
                    $validator->errors()->add(
                        'motivo_anulacion',
                        'Anula primero las cobranzas vigentes aplicadas a esta venta.',
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
            'motivo_anulacion.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo_anulacion.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('motivo_anulacion');

        $this->merge([
            'motivo_anulacion' => is_scalar($reason) && filled($reason)
                ? Str::of((string) $reason)->squish()->toString()
                : null,
        ]);
    }
}
