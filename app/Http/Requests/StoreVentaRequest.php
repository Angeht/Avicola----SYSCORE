<?php

namespace App\Http\Requests;

use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('VENTAS_REGISTRAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => [
                'nullable',
                Rule::exists('clientes', 'id')->where('activo', true),
            ],
            'observacion' => ['nullable', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1', 'max:20'],
            'detalles.*' => ['required', 'array:precio_version_id,precio_aplicado_kg,motivo_ajuste_precio,pesajes'],
            'detalles.*.precio_version_id' => ['required', 'integer', 'distinct:strict', Rule::exists('precio_dia_versiones', 'id')],
            'detalles.*.precio_aplicado_kg' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001', 'max:99999999.9999'],
            'detalles.*.motivo_ajuste_precio' => ['nullable', 'string', 'max:255'],
            'detalles.*.pesajes' => ['required', 'array', 'min:1', 'max:50'],
            'detalles.*.pesajes.*' => ['required', 'array:tipo_jaba_id,cantidad_jabas,cantidad_pollos,peso_bruto_kg,tara_unitaria_aplicada_kg,observacion'],
            'detalles.*.pesajes.*.tipo_jaba_id' => [
                'nullable',
                Rule::exists('tipos_jaba', 'id')->where('activo', true),
            ],
            'detalles.*.pesajes.*.cantidad_jabas' => ['required', 'integer', 'min:0', 'max:99999'],
            'detalles.*.pesajes.*.cantidad_pollos' => ['required', 'integer', 'min:0', 'max:9999999'],
            'detalles.*.pesajes.*.peso_bruto_kg' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
            'detalles.*.pesajes.*.tara_unitaria_aplicada_kg' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:9999999.999'],
            'detalles.*.pesajes.*.observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $details = $this->input('detalles');

                if (! is_array($details)) {
                    return;
                }

                $priceVersionIds = collect($details)
                    ->filter(fn (mixed $detail): bool => is_array($detail))
                    ->pluck('precio_version_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values();
                $currentPrices = DB::table('vw_precio_vigente as pv')
                    ->join('productos as p', 'p.id', '=', 'pv.producto_id')
                    ->where('p.activo', true)
                    ->whereDate('pv.fecha', today()->toDateString())
                    ->whereIn('pv.precio_version_id', $priceVersionIds)
                    ->select(['pv.precio_version_id', 'pv.producto_id', 'pv.precio_kg', 'p.modalidad_venta'])
                    ->get()
                    ->keyBy('precio_version_id');
                $user = $this->user();

                foreach ($details as $detailIndex => $detail) {
                    if (! is_array($detail)) {
                        continue;
                    }

                    $priceVersionId = (int) ($detail['precio_version_id'] ?? 0);
                    $currentPrice = $currentPrices->get($priceVersionId);

                    if ($priceVersionId > 0 && $currentPrice === null) {
                        $validator->errors()->add(
                            "detalles.$detailIndex.precio_version_id",
                            'El precio seleccionado ya no está vigente para la fecha actual.',
                        );
                    }

                    if ($currentPrice !== null && ! $validator->errors()->has("detalles.$detailIndex.precio_aplicado_kg")) {
                        $referencePrice = $this->priceToTenThousandths($currentPrice->precio_kg);
                        $appliedPrice = $this->priceToTenThousandths($detail['precio_aplicado_kg'] ?? 0);

                        if ($referencePrice !== $appliedPrice) {
                            if (! $user instanceof Usuario || ! $user->tienePermiso('PRECIO_VENTA_EDITAR')) {
                                $validator->errors()->add(
                                    "detalles.$detailIndex.precio_aplicado_kg",
                                    'No tienes permiso para modificar el precio vigente.',
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
                        $currentPrice?->modalidad_venta,
                    );

                    if ($currentPrice !== null) {
                        $this->validateStock($validator, $detailIndex, (int) $currentPrice->producto_id, $detail['pesajes'] ?? null);
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
            'cliente_id.exists' => 'El cliente seleccionado no está disponible.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
            'detalles.required' => 'Agrega al menos un producto a la venta.',
            'detalles.array' => 'Los productos enviados no son válidos.',
            'detalles.min' => 'Agrega al menos un producto a la venta.',
            'detalles.max' => 'Puedes incluir hasta 20 productos por venta.',
            'detalles.*.array' => 'Uno de los productos contiene datos no permitidos.',
            'detalles.*.precio_version_id.required' => 'Selecciona el producto y su precio vigente.',
            'detalles.*.precio_version_id.distinct' => 'Cada producto solo puede aparecer una vez en la venta.',
            'detalles.*.precio_version_id.exists' => 'El precio seleccionado no existe.',
            'detalles.*.precio_aplicado_kg.required' => 'Ingresa el precio por kilogramo.',
            'detalles.*.precio_aplicado_kg.numeric' => 'El precio debe ser un número válido.',
            'detalles.*.precio_aplicado_kg.decimal' => 'El precio puede tener hasta cuatro decimales.',
            'detalles.*.precio_aplicado_kg.min' => 'El precio debe ser mayor que cero.',
            'detalles.*.precio_aplicado_kg.max' => 'El precio supera el máximo permitido.',
            'detalles.*.motivo_ajuste_precio.max' => 'El motivo del ajuste no puede superar los 255 caracteres.',
            'detalles.*.pesajes.required' => 'Registra al menos un pesaje para cada producto.',
            'detalles.*.pesajes.array' => 'Los pesajes enviados no son válidos.',
            'detalles.*.pesajes.min' => 'Registra al menos un pesaje para cada producto.',
            'detalles.*.pesajes.max' => 'Puedes registrar hasta 50 pesajes por producto.',
            'detalles.*.pesajes.*.array' => 'Uno de los pesajes contiene datos no permitidos.',
            'detalles.*.pesajes.*.tipo_jaba_id.exists' => 'El tipo de jaba seleccionado no está disponible.',
            'detalles.*.pesajes.*.cantidad_jabas.required' => 'Indica la cantidad de jabas.',
            'detalles.*.pesajes.*.cantidad_jabas.integer' => 'La cantidad de jabas debe ser un entero.',
            'detalles.*.pesajes.*.cantidad_jabas.min' => 'La cantidad de jabas no puede ser negativa.',
            'detalles.*.pesajes.*.cantidad_pollos.required' => 'Indica la cantidad de pollos.',
            'detalles.*.pesajes.*.cantidad_pollos.integer' => 'La cantidad de pollos debe ser un entero.',
            'detalles.*.pesajes.*.cantidad_pollos.min' => 'La cantidad de pollos no puede ser negativa.',
            'detalles.*.pesajes.*.peso_bruto_kg.required' => 'Ingresa el peso bruto.',
            'detalles.*.pesajes.*.peso_bruto_kg.numeric' => 'El peso bruto debe ser un número válido.',
            'detalles.*.pesajes.*.peso_bruto_kg.decimal' => 'El peso bruto puede tener hasta tres decimales.',
            'detalles.*.pesajes.*.peso_bruto_kg.min' => 'El peso bruto debe ser mayor que cero.',
            'detalles.*.pesajes.*.tara_unitaria_aplicada_kg.numeric' => 'La tara debe ser un número válido.',
            'detalles.*.pesajes.*.tara_unitaria_aplicada_kg.decimal' => 'La tara puede tener hasta tres decimales.',
            'detalles.*.pesajes.*.tara_unitaria_aplicada_kg.min' => 'La tara no puede ser negativa.',
            'detalles.*.pesajes.*.observacion.max' => 'La observación del pesaje no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $details = $this->input('detalles');

        $this->merge([
            'cliente_id' => $this->filled('cliente_id') ? $this->integer('cliente_id') : null,
            'observacion' => $this->normalizeText($this->input('observacion')),
            'detalles' => is_array($details)
                ? array_map(fn (mixed $detail): mixed => $this->normalizeDetail($detail), $details)
                : $details,
        ]);
    }

    protected function validateWeighings(
        Validator $validator,
        int|string $detailIndex,
        mixed $weighings,
        ?string $saleMode,
    ): void {
        if (! is_array($weighings)) {
            return;
        }

        foreach ($weighings as $weighingIndex => $weighing) {
            if (! is_array($weighing)) {
                continue;
            }

            $crates = (int) ($weighing['cantidad_jabas'] ?? 0);
            $birds = (int) ($weighing['cantidad_pollos'] ?? 0);
            $appliedTare = (float) ($weighing['tara_unitaria_aplicada_kg'] ?? 0);
            $fieldPrefix = "detalles.$detailIndex.pesajes.$weighingIndex";
            $isWeightOnly = $saleMode === Producto::MODALIDAD_SOLO_PESO;

            if ($isWeightOnly) {
                if ($crates > 0 || filled($weighing['tipo_jaba_id'] ?? null) || $appliedTare > 0) {
                    $validator->errors()->add(
                        "$fieldPrefix.cantidad_jabas",
                        'Los productos de solo peso no utilizan jabas ni tara.',
                    );
                }

                if ($birds > 0) {
                    $validator->errors()->add(
                        "$fieldPrefix.cantidad_pollos",
                        'Los productos de solo peso se registran únicamente en kilogramos.',
                    );
                }
            } elseif (! $validator->errors()->has("$fieldPrefix.cantidad_pollos") && $birds <= 0) {
                $validator->errors()->add(
                    "$fieldPrefix.cantidad_pollos",
                    'Cada pesaje de producto vivo debe incluir al menos un pollo.',
                );
            }

            if (! $isWeightOnly && $crates > 0 && blank($weighing['tipo_jaba_id'] ?? null)) {
                $validator->errors()->add("$fieldPrefix.tipo_jaba_id", 'Selecciona el tipo de jaba para este pesaje.');
            }

            if (! $isWeightOnly && $crates > 0 && $appliedTare <= 0) {
                $validator->errors()->add("$fieldPrefix.tara_unitaria_aplicada_kg", 'La tara debe ser mayor que cero cuando hay jabas.');
            }

            if ($validator->errors()->hasAny([
                "$fieldPrefix.cantidad_jabas",
                "$fieldPrefix.peso_bruto_kg",
                "$fieldPrefix.tara_unitaria_aplicada_kg",
            ])) {
                continue;
            }

            $grossWeightGrams = $this->kilogramsToGrams($weighing['peso_bruto_kg'] ?? 0);
            $totalTareGrams = $crates * $this->kilogramsToGrams($appliedTare);

            if ($grossWeightGrams <= $totalTareGrams) {
                $validator->errors()->add("$fieldPrefix.peso_bruto_kg", 'El peso bruto debe ser mayor que la tara total.');
            }
        }
    }

    protected function validateStock(
        Validator $validator,
        int|string $detailIndex,
        int $productId,
        mixed $weighings,
        ?int $saleId = null,
    ): void {
        if (! is_array($weighings)) {
            return;
        }

        $requestedBirds = 0;
        $requestedGrams = 0;

        foreach ($weighings as $weighing) {
            if (! is_array($weighing)) {
                continue;
            }

            $crates = max(0, (int) ($weighing['cantidad_jabas'] ?? 0));
            $requestedBirds += max(0, (int) ($weighing['cantidad_pollos'] ?? 0));
            $requestedGrams += $this->kilogramsToGrams($weighing['peso_bruto_kg'] ?? 0)
                - ($crates * $this->kilogramsToGrams($weighing['tara_unitaria_aplicada_kg'] ?? 0));
        }

        $stock = DB::table('vw_saldo_mercaderia_actual')
            ->where('producto_id', $productId)
            ->first();
        $originalSale = $saleId === null
            ? null
            : DB::table('vw_totales_venta_detalle')
                ->where('venta_id', $saleId)
                ->where('producto_id', $productId)
                ->selectRaw('COALESCE(SUM(cantidad_pollos), 0) AS cantidad_pollos')
                ->selectRaw('COALESCE(SUM(peso_neto_kg), 0) AS peso_neto_kg')
                ->first();
        $availableBirds = (int) ($stock?->pollos_disponibles ?? 0)
            + (int) ($originalSale?->cantidad_pollos ?? 0);
        $availableGrams = $this->kilogramsToGrams($stock?->kg_disponibles ?? 0)
            + $this->kilogramsToGrams($originalSale?->peso_neto_kg ?? 0);

        if ($stock === null
            || $requestedBirds > $availableBirds
            || $requestedGrams > $availableGrams) {
            $validator->errors()->add(
                "detalles.$detailIndex.pesajes",
                'La venta supera la mercadería disponible para este producto.',
            );
        }
    }

    /**
     * @return array<string, mixed>|mixed
     */
    private function normalizeDetail(mixed $detail): mixed
    {
        if (! is_array($detail)) {
            return $detail;
        }

        $weighings = $detail['pesajes'] ?? null;

        return [
            ...$detail,
            'precio_version_id' => filled($detail['precio_version_id'] ?? null)
                ? (int) $detail['precio_version_id']
                : null,
            'precio_aplicado_kg' => $this->normalizeDecimal($detail['precio_aplicado_kg'] ?? null),
            'motivo_ajuste_precio' => $this->normalizeText($detail['motivo_ajuste_precio'] ?? null),
            'pesajes' => is_array($weighings)
                ? array_map(fn (mixed $weighing): mixed => $this->normalizeWeighing($weighing), $weighings)
                : $weighings,
        ];
    }

    /**
     * @return array<string, mixed>|mixed
     */
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

    protected function normalizeDecimal(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return Str::replace(',', '.', trim((string) $value));
    }

    protected function normalizeText(mixed $value): ?string
    {
        if (! is_scalar($value) || blank($value)) {
            return null;
        }

        return Str::of((string) $value)->squish()->toString();
    }

    protected function priceToTenThousandths(mixed $price): int
    {
        return (int) round((float) $price * 10000);
    }

    protected function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
