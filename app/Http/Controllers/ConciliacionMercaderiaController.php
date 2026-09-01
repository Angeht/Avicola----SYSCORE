<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConciliacionMercaderiaRequest;
use App\Models\AjusteMercaderia;
use App\Models\ConciliacionMercaderia;
use App\Models\Producto;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConciliacionMercaderiaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $search = $request->string('buscar')->trim()->toString();
        $type = in_array($request->string('tipo')->toString(), ['CIERRE', 'EXTRAORDINARIA'], true)
            ? $request->string('tipo')->toString()
            : null;
        $date = $this->operationalDateFilter($request);

        $conciliations = ConciliacionMercaderia::query()
            ->select([
                'id', 'numero_conciliacion', 'producto_id', 'fecha_operacion', 'tipo_conciliacion',
                'usuario_id', 'cantidad_pollos_sistema', 'peso_sistema_kg', 'cantidad_pollos_fisico',
                'peso_fisico_kg', 'observacion', 'realizada_at',
            ])
            ->with([
                'producto:id,nombre',
                'usuario:id,nombres,apellidos,usuario',
            ])
            ->withCount('ajustes')
            ->search($search)
            ->when($type !== null, fn ($query) => $query->where('tipo_conciliacion', $type))
            ->when($date !== null, fn ($query) => $query->whereDate('fecha_operacion', $date))
            ->orderByDesc('realizada_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
        $todaySummary = DB::table('vw_conciliaciones_mercaderia')
            ->whereDate('fecha_operacion', today()->toDateString())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN estado_conciliacion = 'CUADRADO' THEN 1 ELSE 0 END) AS cuadradas")
            ->selectRaw("SUM(CASE WHEN estado_conciliacion = 'CON_DIFERENCIA' THEN 1 ELSE 0 END) AS con_diferencia")
            ->selectRaw('COALESCE(SUM(ABS(diferencia_pollos)), 0) AS diferencia_pollos')
            ->selectRaw('COALESCE(SUM(ABS(diferencia_peso_kg)), 0) AS diferencia_peso_kg')
            ->first();

        return view('conciliaciones-mercaderia.index', [
            'authenticatedUser' => $user,
            'conciliations' => $conciliations,
            'date' => $date,
            'search' => $search,
            'todaySummary' => $todaySummary,
            'type' => $type,
        ]);
    }

    public function create(Request $request): View
    {
        $products = $this->productsWithCurrentStock();
        $preselectedProductId = $products->contains('id', $request->integer('producto'))
            ? $request->integer('producto')
            : null;

        return view('conciliaciones-mercaderia.create', [
            'authenticatedUser' => $this->authenticatedUser($request),
            'preselectedProductId' => $preselectedProductId,
            'products' => $products,
        ]);
    }

    public function store(StoreConciliacionMercaderiaRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $conciliation = DB::transaction(function () use ($user, $validated): ConciliacionMercaderia {
            $product = Producto::query()
                ->whereKey($validated['producto_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            $stock = DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $product->getKey())
                ->first();

            if ($stock === null
                || (int) $stock->pollos_disponibles < 0
                || $this->kilogramsToGrams($stock->kg_disponibles) < 0) {
                throw ValidationException::withMessages([
                    'producto_id' => 'El saldo actual del producto requiere corrección antes de poder conciliarlo.',
                ]);
            }

            $systemBirds = (int) $stock->pollos_disponibles;
            $systemWeightGrams = $this->kilogramsToGrams($stock->kg_disponibles);
            $physicalBirds = (int) $validated['cantidad_pollos_fisico'];
            $physicalWeightGrams = $this->kilogramsToGrams($validated['peso_fisico_kg']);
            $birdDifference = $physicalBirds - $systemBirds;
            $weightDifferenceGrams = $physicalWeightGrams - $systemWeightGrams;
            $requiredTypeCodes = collect([
                $birdDifference > 0 || $weightDifferenceGrams > 0 ? 'AJUSTE_POSITIVO' : null,
                $birdDifference < 0 || $weightDifferenceGrams < 0 ? 'AJUSTE_NEGATIVO' : null,
            ])->filter()->values();
            $adjustmentTypes = $requiredTypeCodes->isEmpty()
                ? collect()
                : TipoAjusteMercaderia::query()
                    ->whereIn('codigo', $requiredTypeCodes)
                    ->where('activo', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('codigo');

            if ($adjustmentTypes->count() !== $requiredTypeCodes->count()) {
                throw ValidationException::withMessages([
                    'producto_id' => 'Los tipos de ajuste automático no están disponibles. Comunícate con un administrador.',
                ]);
            }

            $conciliation = ConciliacionMercaderia::query()->create([
                'numero_conciliacion' => 'TMP-'.Str::ulid(),
                'producto_id' => $product->getKey(),
                'fecha_operacion' => today()->toDateString(),
                'tipo_conciliacion' => $validated['tipo_conciliacion'],
                'usuario_id' => $user->getKey(),
                'cantidad_pollos_sistema' => $systemBirds,
                'peso_sistema_kg' => $systemWeightGrams / 1000,
                'cantidad_pollos_fisico' => $physicalBirds,
                'peso_fisico_kg' => $physicalWeightGrams / 1000,
                'observacion' => $validated['observacion'],
                'realizada_at' => now(),
            ]);
            $conciliation->update([
                'numero_conciliacion' => sprintf('CON-%s-%06d', now()->format('Ymd'), $conciliation->getKey()),
            ]);

            if ($adjustmentTypes->has('AJUSTE_POSITIVO')) {
                $this->createLinkedAdjustment(
                    $conciliation,
                    $adjustmentTypes->get('AJUSTE_POSITIVO'),
                    max(0, $birdDifference),
                    max(0, $weightDifferenceGrams),
                    $user,
                );
            }

            if ($adjustmentTypes->has('AJUSTE_NEGATIVO')) {
                $this->createLinkedAdjustment(
                    $conciliation,
                    $adjustmentTypes->get('AJUSTE_NEGATIVO'),
                    abs(min(0, $birdDifference)),
                    abs(min(0, $weightDifferenceGrams)),
                    $user,
                );
            }

            return $conciliation;
        }, 3);

        return to_route('conciliaciones-mercaderia.show', $conciliation)
            ->with('status', "Conciliación {$conciliation->numero_conciliacion} registrada correctamente.");
    }

    public function show(Request $request, ConciliacionMercaderia $conciliacionMercaderia): View
    {
        $conciliacionMercaderia->load([
            'producto:id,nombre,descripcion,unidad_medida_id',
            'producto.unidadMedida:id,codigo,nombre,simbolo',
            'usuario:id,nombres,apellidos,usuario',
            'ajustes' => fn ($query) => $query
                ->select([
                    'ajustes_mercaderia.id', 'numero_ajuste', 'producto_id', 'tipo_ajuste_id',
                    'cantidad_pollos', 'peso_kg', 'motivo', 'usuario_id', 'fecha_ajuste', 'anulado_at',
                ])
                ->with('tipoAjuste:id,codigo,nombre,naturaleza')
                ->orderBy('ajustes_mercaderia.id'),
        ]);

        return view('conciliaciones-mercaderia.show', [
            'authenticatedUser' => $this->authenticatedUser($request),
            'conciliation' => $conciliacionMercaderia,
            'currentBalance' => DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $conciliacionMercaderia->producto_id)
                ->firstOrFail(),
        ]);
    }

    private function createLinkedAdjustment(
        ConciliacionMercaderia $conciliation,
        TipoAjusteMercaderia $type,
        int $birds,
        int $weightGrams,
        Usuario $user,
    ): void {
        if ($birds === 0 && $weightGrams === 0) {
            return;
        }

        $adjustment = AjusteMercaderia::query()->create([
            'numero_ajuste' => 'TMP-'.Str::ulid(),
            'producto_id' => $conciliation->producto_id,
            'tipo_ajuste_id' => $type->getKey(),
            'cantidad_pollos' => $birds,
            'peso_kg' => $weightGrams / 1000,
            'motivo' => "Diferencia detectada en la conciliación {$conciliation->numero_conciliacion}.",
            'usuario_id' => $user->getKey(),
            'fecha_ajuste' => now(),
        ]);
        $adjustment->update([
            'numero_ajuste' => sprintf('AJU-%s-%06d', now()->format('Ymd'), $adjustment->getKey()),
        ]);
        $conciliation->ajustes()->attach($adjustment->getKey());
    }

    private function productsWithCurrentStock(): Collection
    {
        return DB::table('productos as p')
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
