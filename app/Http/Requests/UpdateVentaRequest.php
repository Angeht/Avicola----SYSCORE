<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVentaRequest extends StoreVentaRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('VENTAS_EDITAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $sale = $this->route('venta');
        $originalClientId = $sale instanceof Venta ? $sale->cliente_id : null;
        $rules['cliente_id'] = [
            'nullable',
            Rule::exists('clientes', 'id')->where(
                fn ($query) => $query
                    ->where('activo', true)
                    ->when($originalClientId !== null, fn ($query) => $query->orWhere('id', $originalClientId)),
            ),
        ];
        $rules['motivo_edicion'] = ['required', 'string', 'min:10', 'max:255'];

        return $rules;
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sale = $this->route('venta');
                $details = $this->input('detalles');
                $user = $this->user();

                if (! $sale instanceof Venta || ! is_array($details)) {
                    return;
                }

                if ($sale->estaAnulada()) {
                    $validator->errors()->add('motivo_edicion', 'Una venta eliminada no puede editarse.');

                    return;
                }

                if ($sale->ajustesCliente()->vigentes()->exists()) {
                    $validator->errors()->add('motivo_edicion', 'Anula primero los ajustes comerciales vigentes para editar esta venta.');

                    return;
                }

                $priceVersionIds = collect($details)
                    ->filter(fn (mixed $detail): bool => is_array($detail))
                    ->pluck('precio_version_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values();
                $availablePrices = DB::table('precio_dia_versiones as pv')
                    ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
                    ->join('productos as p', 'p.id', '=', 'pd.producto_id')
                    ->where('p.activo', true)
                    ->whereDate('pd.fecha', $sale->fecha_venta->toDateString())
                    ->whereIn('pv.id', $priceVersionIds)
                    ->select(['pv.id as precio_version_id', 'pd.producto_id', 'pv.precio_kg', 'p.modalidad_venta'])
                    ->get()
                    ->keyBy('precio_version_id');
                $selectedProductIds = [];

                foreach ($details as $detailIndex => $detail) {
                    if (! is_array($detail)) {
                        continue;
                    }

                    $priceVersionId = (int) ($detail['precio_version_id'] ?? 0);
                    $availablePrice = $availablePrices->get($priceVersionId);

                    if ($priceVersionId > 0 && $availablePrice === null) {
                        $validator->errors()->add(
                            "detalles.$detailIndex.precio_version_id",
                            'El producto o precio ya no está disponible para la fecha de esta venta.',
                        );

                        continue;
                    }

                    if ($availablePrice === null) {
                        continue;
                    }

                    $productId = (int) $availablePrice->producto_id;

                    if (in_array($productId, $selectedProductIds, true)) {
                        $validator->errors()->add(
                            "detalles.$detailIndex.precio_version_id",
                            'Cada producto solo puede aparecer una vez en la venta.',
                        );
                    }

                    $selectedProductIds[] = $productId;

                    if (! $validator->errors()->has("detalles.$detailIndex.precio_aplicado_kg")) {
                        $referencePrice = $this->priceToTenThousandths($availablePrice->precio_kg);
                        $appliedPrice = $this->priceToTenThousandths($detail['precio_aplicado_kg'] ?? 0);

                        if ($referencePrice !== $appliedPrice) {
                            if (! $user instanceof Usuario || ! $user->tienePermiso('PRECIO_VENTA_EDITAR')) {
                                $validator->errors()->add(
                                    "detalles.$detailIndex.precio_aplicado_kg",
                                    'No tienes permiso para modificar el precio de referencia.',
                                );
                            }

                            if (mb_strlen((string) ($detail['motivo_ajuste_precio'] ?? '')) < 10) {
                                $validator->errors()->add(
                                    "detalles.$detailIndex.motivo_ajuste_precio",
                                    'Explica el ajuste de precio con al menos 10 caracteres.',
                                );
                            }
                        }
                    }

                    $this->validateWeighings(
                        $validator,
                        $detailIndex,
                        $detail['pesajes'] ?? null,
                        $availablePrice->modalidad_venta,
                    );
                    $this->validateStock(
                        $validator,
                        $detailIndex,
                        $productId,
                        $detail['pesajes'] ?? null,
                        $sale->getKey(),
                    );
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $paidCents = (int) round((float) DB::table('aplicacion_cobranzas as ac')
                    ->join('cobranzas as c', 'c.id', '=', 'ac.cobranza_id')
                    ->where('ac.venta_id', $sale->getKey())
                    ->whereNull('c.anulada_at')
                    ->sum('ac.monto_aplicado') * 100);

                if ($this->saleTotalCents($details) < $paidCents) {
                    $validator->errors()->add(
                        'detalles',
                        'El nuevo total no puede ser menor que el monto ya cobrado de esta venta.',
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
            ...parent::messages(),
            'motivo_edicion.required' => 'Explica el motivo de la edición.',
            'motivo_edicion.min' => 'El motivo de la edición debe tener al menos 10 caracteres.',
            'motivo_edicion.max' => 'El motivo de la edición no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'motivo_edicion' => $this->normalizeText($this->input('motivo_edicion')),
        ]);
    }

    /** @param array<int, array<string, mixed>> $details */
    private function saleTotalCents(array $details): int
    {
        return collect($details)->sum(function (array $detail): int {
            $netGrams = collect($detail['pesajes'] ?? [])->sum(function (array $weighing): int {
                return $this->kilogramsToGrams($weighing['peso_bruto_kg'] ?? 0)
                    - ((int) ($weighing['cantidad_jabas'] ?? 0)
                        * $this->kilogramsToGrams($weighing['tara_unitaria_aplicada_kg'] ?? 0));
            });

            return (int) round(($netGrams / 1000) * (float) ($detail['precio_aplicado_kg'] ?? 0) * 100);
        });
    }
}
