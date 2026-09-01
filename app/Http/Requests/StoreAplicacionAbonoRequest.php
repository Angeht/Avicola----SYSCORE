<?php

namespace App\Http\Requests;

use App\Models\Cobranza;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAplicacionAbonoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('COBRANZAS_REGISTRAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'aplicaciones' => ['required', 'array', 'min:1', 'max:50'],
            'aplicaciones.*' => ['array:venta_id,monto_aplicado'],
            'aplicaciones.*.venta_id' => ['required', 'integer', 'distinct:strict', Rule::exists('ventas', 'id')],
            'aplicaciones.*.monto_aplicado' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
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

                $collection = $this->route('cobranza');

                if (! $collection instanceof Cobranza) {
                    return;
                }

                if ($collection->tipo !== 'ABONO' || $collection->cliente_id === null || $collection->estaAnulada()) {
                    $validator->errors()->add('aplicaciones', 'Solo puedes distribuir un abono vigente asociado a un cliente.');

                    return;
                }

                $remainingCents = $this->remainingCents($collection);

                if ($remainingCents <= 0) {
                    $validator->errors()->add('aplicaciones', 'Este abono ya no tiene saldo disponible para aplicar.');

                    return;
                }

                $applications = $this->input('aplicaciones', []);

                if (! is_array($applications)) {
                    return;
                }

                $saleIds = collect($applications)->pluck('venta_id')->map(fn (mixed $id): int => (int) $id);
                $sales = DB::table('ventas as v')
                    ->leftJoin('vw_saldos_venta as sv', 'sv.venta_id', '=', 'v.id')
                    ->whereIn('v.id', $saleIds)
                    ->get(['v.id', 'v.cliente_id', 'v.anulada_at', 'sv.saldo_pendiente', 'sv.estado_pago'])
                    ->keyBy('id');
                $alreadyAppliedSaleIds = DB::table('aplicacion_cobranzas')
                    ->where('cobranza_id', $collection->getKey())
                    ->whereIn('venta_id', $saleIds)
                    ->pluck('venta_id')
                    ->map(fn (mixed $id): int => (int) $id);
                $appliedCents = 0;

                foreach ($applications as $index => $application) {
                    $saleId = (int) $application['venta_id'];
                    $sale = $sales->get($saleId);

                    if ($sale === null || $sale->anulada_at !== null || $sale->saldo_pendiente === null || $sale->estado_pago === 'ANULADA') {
                        $validator->errors()->add("aplicaciones.$index.venta_id", 'La venta seleccionada no está disponible para aplicar el abono.');

                        continue;
                    }

                    if ((int) $sale->cliente_id !== (int) $collection->cliente_id) {
                        $validator->errors()->add("aplicaciones.$index.venta_id", 'La venta seleccionada no pertenece al cliente del abono.');
                    }

                    if ($alreadyAppliedSaleIds->contains($saleId)) {
                        $validator->errors()->add("aplicaciones.$index.venta_id", 'Este abono ya fue aplicado a la venta seleccionada.');
                    }

                    $applicationCents = $this->moneyToCents($application['monto_aplicado']);

                    if ($applicationCents > $this->moneyToCents($sale->saldo_pendiente)) {
                        $validator->errors()->add("aplicaciones.$index.monto_aplicado", 'El monto aplicado supera el saldo pendiente de la venta.');
                    }

                    $appliedCents += $applicationCents;
                }

                if ($appliedCents > $remainingCents) {
                    $validator->errors()->add('aplicaciones', 'El total aplicado supera el saldo disponible del abono.');
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
            'aplicaciones.required' => 'Agrega al menos una venta para distribuir el abono.',
            'aplicaciones.array' => 'Las aplicaciones del abono no tienen un formato válido.',
            'aplicaciones.min' => 'Agrega al menos una venta para distribuir el abono.',
            'aplicaciones.max' => 'Solo puedes aplicar el abono a un máximo de 50 ventas.',
            'aplicaciones.*.array' => 'Una aplicación contiene campos no permitidos.',
            'aplicaciones.*.venta_id.required' => 'Selecciona la venta a la que deseas aplicar el abono.',
            'aplicaciones.*.venta_id.integer' => 'La venta seleccionada no es válida.',
            'aplicaciones.*.venta_id.distinct' => 'Cada venta solo puede agregarse una vez.',
            'aplicaciones.*.venta_id.exists' => 'La venta seleccionada no existe.',
            'aplicaciones.*.monto_aplicado.required' => 'Ingresa el monto que se aplicará a la venta.',
            'aplicaciones.*.monto_aplicado.numeric' => 'El monto aplicado debe ser un número válido.',
            'aplicaciones.*.monto_aplicado.decimal' => 'El monto aplicado puede tener hasta dos decimales.',
            'aplicaciones.*.monto_aplicado.min' => 'El monto aplicado debe ser mayor que cero.',
            'aplicaciones.*.monto_aplicado.max' => 'El monto aplicado supera el máximo permitido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $applications = $this->input('aplicaciones', []);

        if (is_array($applications)) {
            $applications = collect($applications)
                ->filter(function (mixed $application): bool {
                    if (! is_array($application)) {
                        return true;
                    }

                    return filled($application['venta_id'] ?? null)
                        || filled($application['monto_aplicado'] ?? null);
                })
                ->map(function (mixed $application): mixed {
                    if (! is_array($application)) {
                        return $application;
                    }

                    $application['venta_id'] = $this->normalizeIdentifier($application['venta_id'] ?? null);
                    $application['monto_aplicado'] = $this->normalizeMoney($application['monto_aplicado'] ?? null);

                    return $application;
                })
                ->values()
                ->all();
        }

        $this->merge(['aplicaciones' => $applications]);
    }

    private function remainingCents(Cobranza $collection): int
    {
        $applied = DB::table('aplicacion_cobranzas')
            ->where('cobranza_id', $collection->getKey())
            ->sum('monto_aplicado');

        return max(0, $this->moneyToCents($collection->monto_total) - $this->moneyToCents($applied));
    }

    private function normalizeIdentifier(mixed $value): mixed
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $value;
    }

    private function normalizeMoney(mixed $value): mixed
    {
        return is_scalar($value)
            ? Str::replace(',', '.', Str::of((string) $value)->trim()->toString())
            : $value;
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
