<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Http\Requests\UpdateVentaRequest;
use App\Models\Cliente;
use App\Models\PesajeVenta;
use App\Models\PrecioDiaVersion;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TipoJaba;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $search = $request->string('buscar')->trim()->toString();
        $status = in_array($request->string('estado')->toString(), ['activas', 'anuladas', 'todas'], true)
            ? $request->string('estado')->toString()
            : 'activas';
        $date = $this->operationalDateFilter($request);

        $sales = Venta::query()
            ->select(['id', 'numero_venta', 'cliente_id', 'usuario_id', 'sesion_caja_id', 'fecha_venta', 'anulada_por', 'anulada_at'])
            ->with([
                'cliente:id,nombres_razon_social,nro_documento',
                'usuario:id,nombres,apellidos,usuario',
                'anuladaPor:id,nombres,apellidos,usuario',
            ])
            ->search($search)
            ->when($status === 'activas', fn ($query) => $query->whereNull('anulada_at'))
            ->when($status === 'anuladas', fn ($query) => $query->whereNotNull('anulada_at'))
            ->when($date !== null, fn ($query) => $query->whereDate('fecha_venta', $date))
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
        $saleIds = $sales->getCollection()->pluck('id');
        $totals = DB::table('vw_totales_venta')
            ->whereIn('venta_id', $saleIds)
            ->get()
            ->keyBy('venta_id');
        $balances = DB::table('vw_saldos_venta')
            ->whereIn('venta_id', $saleIds)
            ->get()
            ->keyBy('venta_id');

        return view('ventas.index', [
            'authenticatedUser' => $user,
            'balances' => $balances,
            'date' => $date,
            'sales' => $sales,
            'search' => $search,
            'status' => $status,
            'todaySummary' => DB::table('vw_resumen_diario_ventas')
                ->where('fecha', today()->toDateString())
                ->first(),
            'totals' => $totals,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->authenticatedUser($request);

        return view('ventas.create', [
            'authenticatedUser' => $user,
            'canEditPrice' => $user->tienePermiso('PRECIO_VENTA_EDITAR'),
            'cashSession' => SesionCaja::query()
                ->where('usuario_id', $user->getKey())
                ->where('fecha_operacion', today()->toDateString())
                ->abiertas()
                ->orderByDesc('id')
                ->first(),
            'clients' => Cliente::query()
                ->select(['id', 'nombres_razon_social', 'nro_documento'])
                ->where('activo', true)
                ->orderBy('nombres_razon_social')
                ->get(),
            'crateTypes' => TipoJaba::query()
                ->select(['id', 'nombre', 'tara_referencial_kg'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'initialDetails' => null,
            'priceOptions' => $this->priceOptions(today()->toDateString()),
            'sale' => null,
        ]);
    }

    public function store(StoreVentaRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $sale = DB::transaction(function () use ($user, $validated): Venta {
            if ($validated['cliente_id'] !== null) {
                Cliente::query()
                    ->whereKey($validated['cliente_id'])
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $priceVersionIds = collect($validated['detalles'])
                ->pluck('precio_version_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values();
            $productIds = DB::table('vw_precio_vigente')
                ->whereDate('fecha', today()->toDateString())
                ->whereIn('precio_version_id', $priceVersionIds)
                ->pluck('producto_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();

            $lockedProducts = Producto::query()
                ->whereKey($productIds)
                ->where('activo', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($lockedProducts->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'detalles' => 'Uno de los productos ya no está disponible.',
                ]);
            }

            PrecioDiaVersion::query()
                ->whereKey($priceVersionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $currentPrices = DB::table('vw_precio_vigente')
                ->join('productos as p', 'p.id', '=', 'vw_precio_vigente.producto_id')
                ->whereDate('fecha', today()->toDateString())
                ->whereIn('precio_version_id', $priceVersionIds)
                ->get(['precio_version_id', 'producto_id', 'precio_kg', 'p.modalidad_venta'])
                ->keyBy('precio_version_id');

            if ($currentPrices->count() !== $priceVersionIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'detalles' => 'Uno de los precios cambió. Actualiza la venta antes de continuar.',
                ]);
            }

            $this->ensureStockIsAvailable($validated['detalles'], $currentPrices);
            $this->ensurePriceAdjustmentsAreAuthorized($user, $validated['detalles'], $currentPrices);

            $cashSession = SesionCaja::query()
                ->where('usuario_id', $user->getKey())
                ->where('fecha_operacion', today()->toDateString())
                ->abiertas()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $sale = Venta::query()->create([
                'numero_venta' => 'TMP-'.Str::ulid(),
                'cliente_id' => $validated['cliente_id'],
                'usuario_id' => $user->getKey(),
                'sesion_caja_id' => $cashSession?->getKey(),
                'fecha_venta' => now(),
                'observacion' => $validated['observacion'],
            ]);

            $sale->update([
                'numero_venta' => sprintf('VEN-%s-%06d', now()->format('Ymd'), $sale->getKey()),
            ]);

            $this->createSaleDetails($sale, $validated['detalles'], $currentPrices);

            return $sale;
        }, 3);

        return to_route('ventas.show', $sale)
            ->with('status', "Venta {$sale->numero_venta} registrada correctamente.");
    }

    public function edit(Request $request, Venta $venta): View
    {
        abort_if($venta->estaAnulada(), 409, 'Una venta eliminada no puede editarse.');
        $user = $this->authenticatedUser($request);
        $venta->load([
            'detalles' => fn ($query) => $query
                ->with(['pesajes' => fn ($query) => $query->orderBy('id')])
                ->orderBy('id'),
        ]);

        return view('ventas.create', [
            'authenticatedUser' => $user,
            'canEditPrice' => $user->tienePermiso('PRECIO_VENTA_EDITAR'),
            'cashSession' => null,
            'clients' => Cliente::query()
                ->select(['id', 'nombres_razon_social', 'nro_documento'])
                ->where(fn ($query) => $query
                    ->where('activo', true)
                    ->when($venta->cliente_id !== null, fn ($query) => $query->orWhereKey($venta->cliente_id)))
                ->orderBy('nombres_razon_social')
                ->get(),
            'crateTypes' => TipoJaba::query()
                ->select(['id', 'nombre', 'tara_referencial_kg'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'initialDetails' => $venta->detalles->map(fn (VentaDetalle $detail): array => [
                'precio_version_id' => $detail->precio_version_id,
                'precio_aplicado_kg' => $detail->precio_aplicado_kg,
                'motivo_ajuste_precio' => $detail->motivo_ajuste_precio,
                'pesajes' => $detail->pesajes->map(fn (PesajeVenta $weighing): array => [
                    'tipo_jaba_id' => $weighing->tipo_jaba_id,
                    'cantidad_jabas' => $weighing->cantidad_jabas,
                    'cantidad_pollos' => $weighing->cantidad_pollos,
                    'peso_bruto_kg' => $weighing->peso_bruto_kg,
                    'tara_unitaria_aplicada_kg' => $weighing->tara_unitaria_aplicada_kg,
                    'observacion' => $weighing->observacion,
                ])->all(),
            ])->all(),
            'priceOptions' => $this->priceOptions($venta->fecha_venta->toDateString(), $venta),
            'sale' => $venta,
        ]);
    }

    public function update(UpdateVentaRequest $request, Venta $venta): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $sale = DB::transaction(function () use ($user, $validated, $venta): Venta {
            $lockedSale = Venta::query()
                ->whereKey($venta->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->estaAnulada()) {
                throw ValidationException::withMessages([
                    'motivo_edicion' => 'Una venta eliminada no puede editarse.',
                ]);
            }

            if ($validated['cliente_id'] !== null) {
                Cliente::query()
                    ->whereKey($validated['cliente_id'])
                    ->where(fn ($query) => $query
                        ->where('activo', true)
                        ->when(
                            $lockedSale->cliente_id !== null,
                            fn ($query) => $query->orWhereKey($lockedSale->cliente_id),
                        ))
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $priceVersionIds = collect($validated['detalles'])
                ->pluck('precio_version_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            PrecioDiaVersion::query()
                ->whereKey($priceVersionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $availablePrices = DB::table('precio_dia_versiones as pv')
                ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
                ->join('productos as p', 'p.id', '=', 'pd.producto_id')
                ->where('p.activo', true)
                ->whereDate('pd.fecha', $lockedSale->fecha_venta->toDateString())
                ->whereIn('pv.id', $priceVersionIds)
                ->select(['pv.id as precio_version_id', 'pd.producto_id', 'pv.precio_kg', 'p.modalidad_venta'])
                ->get()
                ->keyBy('precio_version_id');

            if ($availablePrices->count() !== $priceVersionIds->count()
                || $availablePrices->pluck('producto_id')->unique()->count() !== $priceVersionIds->count()) {
                throw ValidationException::withMessages([
                    'detalles' => 'Uno de los productos o precios ya no está disponible para esta venta.',
                ]);
            }

            $originalProductIds = DB::table('venta_detalles as vd')
                ->join('precio_dia_versiones as pv', 'pv.id', '=', 'vd.precio_version_id')
                ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
                ->where('vd.venta_id', $lockedSale->getKey())
                ->pluck('pd.producto_id');
            $productIds = $availablePrices->pluck('producto_id')
                ->merge($originalProductIds)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            Producto::query()
                ->whereKey($productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $this->ensureStockIsAvailable($validated['detalles'], $availablePrices, $lockedSale->getKey());
            $this->ensurePriceAdjustmentsAreAuthorized($user, $validated['detalles'], $availablePrices);

            $activeCollections = DB::table('aplicacion_cobranzas as ac')
                ->join('cobranzas as c', 'c.id', '=', 'ac.cobranza_id')
                ->where('ac.venta_id', $lockedSale->getKey())
                ->whereNull('c.anulada_at')
                ->lockForUpdate()
                ->get(['ac.monto_aplicado']);
            $paidCents = (int) round($activeCollections->sum('monto_aplicado') * 100);

            if ($this->saleTotalCents($validated['detalles']) < $paidCents) {
                throw ValidationException::withMessages([
                    'detalles' => 'El nuevo total no puede ser menor que el monto ya cobrado de esta venta.',
                ]);
            }

            $detailsToDelete = VentaDetalle::query()
                ->where('venta_id', $lockedSale->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $weighingsToDelete = PesajeVenta::query()
                ->whereIn('venta_detalle_id', $detailsToDelete->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('venta_detalle_id');

            foreach ($detailsToDelete as $detailToDelete) {
                foreach ($weighingsToDelete->get($detailToDelete->getKey(), collect()) as $weighingToDelete) {
                    $weighingToDelete->delete();
                }

                $detailToDelete->delete();
            }

            $lockedSale->update([
                'cliente_id' => $validated['cliente_id'],
                'observacion' => $validated['observacion'],
                'editada_por' => $user->getKey(),
                'editada_at' => now(),
                'motivo_edicion' => $validated['motivo_edicion'],
            ]);
            $this->createSaleDetails($lockedSale, $validated['detalles'], $availablePrices);

            return $lockedSale;
        }, 3);

        return to_route('ventas.show', $sale)
            ->with('status', "Venta {$sale->numero_venta} actualizada correctamente.");
    }

    public function show(Request $request, Venta $venta): View
    {
        $venta->load([
            'cliente:id,nombres_razon_social,nro_documento,telefono,direccion',
            'usuario:id,nombres,apellidos,usuario',
            'sesionCaja:id,usuario_id,fecha_operacion,apertura_at,cierre_at',
            'anuladaPor:id,nombres,apellidos,usuario',
            'editadaPor:id,nombres,apellidos,usuario',
            'detalles' => fn ($query) => $query
                ->select(['id', 'venta_id', 'precio_version_id', 'precio_aplicado_kg', 'motivo_ajuste_precio'])
                ->with([
                    'precioVersion:id,precio_dia_id,precio_kg,vigente_desde',
                    'precioVersion.precioDia:id,producto_id,fecha',
                    'precioVersion.precioDia.producto:id,nombre,modalidad_venta',
                    'pesajes' => fn ($query) => $query
                        ->select(['id', 'venta_detalle_id', 'tipo_pesaje', 'tipo_jaba_id', 'cantidad_jabas', 'cantidad_pollos', 'peso_bruto_kg', 'tara_unitaria_aplicada_kg', 'observacion'])
                        ->with('tipoJaba:id,nombre')
                        ->orderBy('id'),
                ])
                ->orderBy('id'),
        ]);

        return view('ventas.show', [
            'authenticatedUser' => $this->authenticatedUser($request),
            'balance' => DB::table('vw_saldos_venta')->where('venta_id', $venta->getKey())->firstOrFail(),
            'detailTotals' => DB::table('vw_totales_venta_detalle')
                ->where('venta_id', $venta->getKey())
                ->get()
                ->keyBy('venta_detalle_id'),
            'sale' => $venta,
            'totals' => DB::table('vw_totales_venta')->where('venta_id', $venta->getKey())->firstOrFail(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @param  Collection<int|string, object>  $currentPrices
     */
    private function ensureStockIsAvailable(array $details, Collection $currentPrices, ?int $saleId = null): void
    {
        foreach ($details as $detailIndex => $detail) {
            $currentPrice = $currentPrices->get($detail['precio_version_id']);
            $stock = DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $currentPrice->producto_id)
                ->first();
            $tracksBirds = $currentPrice->modalidad_venta === Producto::MODALIDAD_PESAJE_VIVO;
            $requestedBirds = collect($detail['pesajes'])->sum('cantidad_pollos');
            $requestedGrams = collect($detail['pesajes'])->sum(function (array $weighing): int {
                return $this->kilogramsToGrams($weighing['peso_bruto_kg'])
                    - ($weighing['cantidad_jabas'] * $this->kilogramsToGrams($weighing['tara_unitaria_aplicada_kg']));
            });
            $originalSale = $saleId === null
                ? null
                : DB::table('vw_totales_venta_detalle')
                    ->where('venta_id', $saleId)
                    ->where('producto_id', $currentPrice->producto_id)
                    ->selectRaw('COALESCE(SUM(cantidad_pollos), 0) AS cantidad_pollos')
                    ->selectRaw('COALESCE(SUM(peso_neto_kg), 0) AS peso_neto_kg')
                    ->first();
            $availableBirds = (int) ($stock?->pollos_disponibles ?? 0)
                + (int) ($originalSale?->cantidad_pollos ?? 0);
            $availableGrams = $this->kilogramsToGrams($stock?->kg_disponibles ?? 0)
                + $this->kilogramsToGrams($originalSale?->peso_neto_kg ?? 0);

            if ($stock === null
                || ($tracksBirds && $requestedBirds > $availableBirds)
                || $requestedGrams > $availableGrams) {
                throw ValidationException::withMessages([
                    "detalles.$detailIndex.pesajes" => 'La venta supera la mercadería disponible para este producto.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @param  Collection<int|string, object>  $currentPrices
     */
    private function ensurePriceAdjustmentsAreAuthorized(Usuario $user, array $details, Collection $currentPrices): void
    {
        foreach ($details as $detailIndex => $detail) {
            $referencePrice = $this->priceToTenThousandths($currentPrices->get($detail['precio_version_id'])->precio_kg);
            $appliedPrice = $this->priceToTenThousandths($detail['precio_aplicado_kg']);

            if ($referencePrice === $appliedPrice) {
                continue;
            }

            if (! $user->tienePermiso('PRECIO_VENTA_EDITAR')) {
                throw ValidationException::withMessages([
                    "detalles.$detailIndex.precio_aplicado_kg" => 'No tienes permiso para modificar el precio vigente.',
                ]);
            }

            if (mb_strlen((string) $detail['motivo_ajuste_precio']) < 10) {
                throw ValidationException::withMessages([
                    "detalles.$detailIndex.motivo_ajuste_precio" => 'Explica el ajuste de precio con al menos 10 caracteres.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @param  Collection<int|string, object>  $prices
     */
    private function createSaleDetails(Venta $sale, array $details, Collection $prices): void
    {
        foreach ($details as $detail) {
            $price = $prices->get($detail['precio_version_id']);
            $isWeightOnly = $price->modalidad_venta === Producto::MODALIDAD_SOLO_PESO;
            $saleDetail = $sale->detalles()->create([
                'precio_version_id' => $detail['precio_version_id'],
                'precio_aplicado_kg' => $detail['precio_aplicado_kg'],
                'motivo_ajuste_precio' => $detail['motivo_ajuste_precio'],
            ]);
            $saleDetail->pesajes()->createMany(collect($detail['pesajes'])
                ->map(fn (array $weighing): array => [
                    'tipo_pesaje' => ! $isWeightOnly && $weighing['cantidad_jabas'] > 0 ? 'CON_JABA' : 'DIRECTO',
                    'tipo_jaba_id' => ! $isWeightOnly && $weighing['cantidad_jabas'] > 0 ? $weighing['tipo_jaba_id'] : null,
                    'cantidad_jabas' => $isWeightOnly ? 0 : $weighing['cantidad_jabas'],
                    'cantidad_pollos' => $isWeightOnly ? 0 : $weighing['cantidad_pollos'],
                    'peso_bruto_kg' => $weighing['peso_bruto_kg'],
                    'tara_unitaria_aplicada_kg' => ! $isWeightOnly && $weighing['cantidad_jabas'] > 0
                        ? $weighing['tara_unitaria_aplicada_kg']
                        : 0,
                    'observacion' => $weighing['observacion'],
                ])
                ->all());
        }
    }

    /** @return Collection<int, object> */
    private function priceOptions(string $date, ?Venta $sale = null): Collection
    {
        $options = DB::table('vw_precio_vigente as pv')
            ->join('productos as p', 'p.id', '=', 'pv.producto_id')
            ->join('vw_saldo_mercaderia_actual as sm', 'sm.producto_id', '=', 'p.id')
            ->where('p.activo', true)
            ->whereDate('pv.fecha', $date)
            ->select([
                'pv.precio_version_id',
                'pv.producto_id',
                'pv.precio_kg',
                'p.nombre as producto',
                'p.modalidad_venta',
                'sm.pollos_disponibles',
                'sm.kg_disponibles',
            ])
            ->get()
            ->keyBy('producto_id');

        if ($sale === null) {
            return $options->sortBy('producto')->values();
        }

        $originalOptions = DB::table('venta_detalles as vd')
            ->join('precio_dia_versiones as pv', 'pv.id', '=', 'vd.precio_version_id')
            ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
            ->join('productos as p', 'p.id', '=', 'pd.producto_id')
            ->join('vw_saldo_mercaderia_actual as sm', 'sm.producto_id', '=', 'p.id')
            ->where('vd.venta_id', $sale->getKey())
            ->where('p.activo', true)
            ->select([
                'pv.id as precio_version_id',
                'pd.producto_id',
                'pv.precio_kg',
                'p.nombre as producto',
                'p.modalidad_venta',
                'sm.pollos_disponibles',
                'sm.kg_disponibles',
            ])
            ->get();

        foreach ($originalOptions as $originalOption) {
            $options->put($originalOption->producto_id, $originalOption);
        }

        $restoredStock = DB::table('vw_totales_venta_detalle')
            ->where('venta_id', $sale->getKey())
            ->groupBy('producto_id')
            ->select('producto_id')
            ->selectRaw('SUM(cantidad_pollos) AS cantidad_pollos')
            ->selectRaw('SUM(peso_neto_kg) AS peso_neto_kg')
            ->get()
            ->keyBy('producto_id');

        return $options
            ->map(function (object $option) use ($restoredStock): object {
                $original = $restoredStock->get($option->producto_id);
                $option->pollos_disponibles = (int) $option->pollos_disponibles
                    + (int) ($original?->cantidad_pollos ?? 0);
                $option->kg_disponibles = round(
                    (float) $option->kg_disponibles + (float) ($original?->peso_neto_kg ?? 0),
                    3,
                );

                return $option;
            })
            ->sortBy('producto')
            ->values();
    }

    /** @param array<int, array<string, mixed>> $details */
    private function saleTotalCents(array $details): int
    {
        return collect($details)->sum(function (array $detail): int {
            $netGrams = collect($detail['pesajes'])->sum(function (array $weighing): int {
                return $this->kilogramsToGrams($weighing['peso_bruto_kg'])
                    - ($weighing['cantidad_jabas'] * $this->kilogramsToGrams($weighing['tara_unitaria_aplicada_kg']));
            });

            return (int) round(($netGrams / 1000) * (float) $detail['precio_aplicado_kg'] * 100);
        });
    }

    private function authenticatedUser(Request $request): Usuario
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $user->loadMissing('roles.permisos');

        return $user;
    }

    private function priceToTenThousandths(mixed $price): int
    {
        return (int) round((float) $price * 10000);
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
