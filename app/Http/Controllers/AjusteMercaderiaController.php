<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAjusteMercaderiaRequest;
use App\Models\AjusteMercaderia;
use App\Models\Producto;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AjusteMercaderiaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $search = $request->string('buscar')->trim()->toString();
        $status = in_array($request->string('estado')->toString(), ['vigentes', 'anulados', 'todos'], true)
            ? $request->string('estado')->toString()
            : 'vigentes';
        $nature = in_array($request->string('naturaleza')->toString(), ['ENTRADA', 'SALIDA'], true)
            ? $request->string('naturaleza')->toString()
            : null;
        $date = $this->operationalDateFilter($request);

        $adjustments = AjusteMercaderia::query()
            ->select(['id', 'numero_ajuste', 'producto_id', 'tipo_ajuste_id', 'cantidad_pollos', 'peso_kg', 'motivo', 'usuario_id', 'fecha_ajuste', 'anulado_por', 'anulado_at'])
            ->with([
                'producto:id,nombre',
                'tipoAjuste:id,codigo,nombre,naturaleza',
                'usuario:id,nombres,apellidos,usuario',
                'anuladoPor:id,nombres,apellidos,usuario',
            ])
            ->search($search)
            ->when($status === 'vigentes', fn ($query) => $query->whereNull('anulado_at'))
            ->when($status === 'anulados', fn ($query) => $query->whereNotNull('anulado_at'))
            ->when($nature !== null, fn ($query) => $query->whereHas(
                'tipoAjuste',
                fn ($query) => $query->where('naturaleza', $nature),
            ))
            ->when($date !== null, fn ($query) => $query->whereDate('fecha_ajuste', $date))
            ->orderByDesc('fecha_ajuste')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
        $todaySummary = DB::table('ajustes_mercaderia as a')
            ->join('tipos_ajuste_mercaderia as ta', 'ta.id', '=', 'a.tipo_ajuste_id')
            ->whereDate('a.fecha_ajuste', today()->toDateString())
            ->selectRaw('SUM(CASE WHEN a.anulado_at IS NULL THEN 1 ELSE 0 END) AS cantidad_ajustes')
            ->selectRaw("COALESCE(SUM(CASE WHEN a.anulado_at IS NULL AND ta.naturaleza = 'ENTRADA' THEN a.cantidad_pollos ELSE 0 END), 0) AS pollos_entrada")
            ->selectRaw("COALESCE(SUM(CASE WHEN a.anulado_at IS NULL AND ta.naturaleza = 'SALIDA' THEN a.cantidad_pollos ELSE 0 END), 0) AS pollos_salida")
            ->selectRaw("COALESCE(SUM(CASE WHEN a.anulado_at IS NULL AND ta.naturaleza = 'ENTRADA' THEN a.peso_kg ELSE 0 END), 0) AS kg_entrada")
            ->selectRaw("COALESCE(SUM(CASE WHEN a.anulado_at IS NULL AND ta.naturaleza = 'SALIDA' THEN a.peso_kg ELSE 0 END), 0) AS kg_salida")
            ->selectRaw('SUM(CASE WHEN a.anulado_at IS NOT NULL THEN 1 ELSE 0 END) AS cantidad_anulados')
            ->first();

        $stockBalances = DB::table('vw_saldo_mercaderia_actual')->orderBy('producto')->get();

        return view('mercaderia.index', [
            'adjustments' => $adjustments,
            'authenticatedUser' => $user,
            'date' => $date,
            'nature' => $nature,
            'search' => $search,
            'status' => $status,
            'productsToReview' => $stockBalances->where('requiere_revision', true)->count(),
            'stockBalances' => $stockBalances,
            'todaySummary' => $todaySummary,
            'totalStockBirds' => (int) $stockBalances->sum('pollos_disponibles'),
            'totalStockKilograms' => (float) $stockBalances->sum('kg_disponibles'),
        ]);
    }

    public function create(Request $request): View
    {
        $products = DB::table('productos as p')
            ->leftJoin('vw_saldo_mercaderia_actual as sm', 'sm.producto_id', '=', 'p.id')
            ->where('p.activo', true)
            ->select([
                'p.id',
                'p.nombre',
                DB::raw('COALESCE(sm.pollos_disponibles, 0) as pollos_disponibles'),
                DB::raw('COALESCE(sm.kg_disponibles, 0) as kg_disponibles'),
                DB::raw('COALESCE(sm.requiere_revision, 0) as requiere_revision'),
            ])
            ->orderBy('p.nombre')
            ->get();
        $preselectedProductId = $products->contains('id', $request->integer('producto'))
            ? $request->integer('producto')
            : null;

        return view('mercaderia.create', [
            'adjustmentTypes' => TipoAjusteMercaderia::query()
                ->select(['id', 'codigo', 'nombre', 'naturaleza', 'requiere_motivo'])
                ->where('activo', true)
                ->orderBy('naturaleza')
                ->orderBy('nombre')
                ->get(),
            'authenticatedUser' => $this->authenticatedUser($request),
            'preselectedProductId' => $preselectedProductId,
            'products' => $products,
        ]);
    }

    public function store(StoreAjusteMercaderiaRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $adjustment = DB::transaction(function () use ($user, $validated): AjusteMercaderia {
            $product = Producto::query()
                ->whereKey($validated['producto_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            $adjustmentType = TipoAjusteMercaderia::query()
                ->whereKey($validated['tipo_ajuste_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $validated['cantidad_pollos'] === 0
                && $this->kilogramsToGrams($validated['peso_kg']) === 0) {
                throw ValidationException::withMessages([
                    'cantidad_pollos' => 'Ingresa al menos una cantidad de aves o un peso mayor que cero.',
                ]);
            }

            if ($adjustmentType->codigo === 'SALDO_INICIAL'
                && DB::table('vw_movimientos_mercaderia')->where('producto_id', $product->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'tipo_ajuste_id' => 'El saldo inicial solo puede registrarse antes del primer movimiento del producto.',
                ]);
            }

            $stock = DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $product->getKey())
                ->first();
            $this->ensureOutgoingStockIsAvailable($adjustmentType, $validated, $stock);

            $adjustment = AjusteMercaderia::query()->create([
                'numero_ajuste' => 'TMP-'.Str::ulid(),
                'producto_id' => $product->getKey(),
                'tipo_ajuste_id' => $adjustmentType->getKey(),
                'cantidad_pollos' => $validated['cantidad_pollos'],
                'peso_kg' => $validated['peso_kg'],
                'motivo' => $validated['motivo'],
                'usuario_id' => $user->getKey(),
                'fecha_ajuste' => now(),
            ]);

            $adjustment->update([
                'numero_ajuste' => sprintf('AJU-%s-%06d', now()->format('Ymd'), $adjustment->getKey()),
            ]);

            return $adjustment;
        }, 3);

        return to_route('mercaderia.show', $adjustment)
            ->with('status', "Ajuste {$adjustment->numero_ajuste} registrado correctamente.");
    }

    public function show(Request $request, AjusteMercaderia $ajusteMercaderia): View
    {
        $ajusteMercaderia->load([
            'producto:id,nombre,descripcion,unidad_medida_id',
            'producto.unidadMedida:id,codigo,nombre,simbolo',
            'tipoAjuste:id,codigo,nombre,naturaleza,requiere_motivo',
            'usuario:id,nombres,apellidos,usuario',
            'anuladoPor:id,nombres,apellidos,usuario',
        ]);
        $blockReason = $this->cancellationBlockReason($ajusteMercaderia);

        return view('mercaderia.show', [
            'adjustment' => $ajusteMercaderia,
            'authenticatedUser' => $this->authenticatedUser($request),
            'balance' => DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $ajusteMercaderia->producto_id)
                ->firstOrFail(),
            'canCancel' => ! $ajusteMercaderia->estaAnulado() && $blockReason === null,
            'cancellationBlockReason' => $blockReason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureOutgoingStockIsAvailable(TipoAjusteMercaderia $type, array $validated, ?object $stock): void
    {
        if ($type->naturaleza !== 'SALIDA' || $stock === null) {
            return;
        }

        $birds = (int) $validated['cantidad_pollos'];
        $weightGrams = $this->kilogramsToGrams($validated['peso_kg']);

        if ($birds > 0 && $birds > (int) $stock->pollos_disponibles) {
            throw ValidationException::withMessages([
                'cantidad_pollos' => 'La salida supera las aves disponibles del producto.',
            ]);
        }

        if ($weightGrams > 0 && $weightGrams > $this->kilogramsToGrams($stock->kg_disponibles)) {
            throw ValidationException::withMessages([
                'peso_kg' => 'La salida supera el peso disponible del producto.',
            ]);
        }
    }

    private function cancellationBlockReason(AjusteMercaderia $adjustment): ?string
    {
        if ($adjustment->estaAnulado()) {
            return null;
        }

        if (DB::table('conciliacion_ajuste')->where('ajuste_id', $adjustment->getKey())->exists()) {
            return 'Este ajuste está vinculado a una conciliación de mercadería y no puede anularse.';
        }

        if (! $adjustment->tipoAjuste->esEntrada()) {
            return null;
        }

        $stock = DB::table('vw_saldo_mercaderia_actual')
            ->where('producto_id', $adjustment->producto_id)
            ->first();

        if ($stock !== null
            && ($adjustment->cantidad_pollos > (int) $stock->pollos_disponibles
                || $this->kilogramsToGrams($adjustment->peso_kg) > $this->kilogramsToGrams($stock->kg_disponibles))) {
            return 'La mercadería de esta entrada ya fue utilizada y el ajuste no puede anularse.';
        }

        return null;
    }

    private function authenticatedUser(Request $request): Usuario
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        return $user;
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
