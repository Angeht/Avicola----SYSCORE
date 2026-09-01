<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrecioDiaRequest;
use App\Models\PrecioDia;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrecioDiaController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->filterDate($request);
        $search = $request->string('buscar')->trim()->toString();

        $prices = PrecioDia::query()
            ->select(['id', 'producto_id', 'fecha', 'created_at'])
            ->with([
                'producto:id,nombre,activo',
                'versionVigente' => fn ($query) => $query->select([
                    'precio_dia_versiones.id',
                    'precio_dia_versiones.precio_dia_id',
                    'precio_dia_versiones.precio_kg',
                    'precio_dia_versiones.vigente_desde',
                    'precio_dia_versiones.registrado_por',
                ]),
                'versionVigente.registradoPor:id,nombres,apellidos,usuario',
            ])
            ->withCount('versiones')
            ->whereDate('fecha', $date)
            ->search($search)
            ->orderBy(Producto::query()
                ->select('nombre')
                ->whereColumn('productos.id', 'precios_dia.producto_id'))
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $activeProductCount = Producto::query()->where('activo', true)->count();
        $pricedProductCount = PrecioDia::query()
            ->whereDate('fecha', $date)
            ->whereHas('producto', fn ($query) => $query->where('activo', true))
            ->count();

        return view('precios-dia.index', [
            'activeProductCount' => $activeProductCount,
            'date' => $date,
            'missingProductCount' => max(0, $activeProductCount - $pricedProductCount),
            'pricedProductCount' => $pricedProductCount,
            'prices' => $prices,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        return view('precios-dia.create', [
            'preselectedProductId' => $request->integer('producto') ?: null,
            'products' => Producto::query()
                ->select(['id', 'nombre'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(StorePrecioDiaRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        $validated = $request->validated();

        $priceDay = DB::transaction(function () use ($user, $validated): PrecioDia {
            Producto::query()
                ->whereKey($validated['producto_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();

            $priceDay = PrecioDia::query()
                ->where('producto_id', $validated['producto_id'])
                ->whereDate('fecha', $validated['fecha'])
                ->lockForUpdate()
                ->first();

            if ($priceDay === null) {
                $priceDay = PrecioDia::query()->create([
                    'producto_id' => $validated['producto_id'],
                    'fecha' => $validated['fecha'],
                ]);
            }

            $currentVersion = $priceDay->versiones()
                ->orderByDesc('vigente_desde')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($currentVersion !== null && sprintf('%.4F', (float) $currentVersion->precio_kg) === sprintf('%.4F', (float) $validated['precio_kg'])) {
                throw ValidationException::withMessages([
                    'precio_kg' => 'El nuevo precio debe ser diferente al precio vigente.',
                ]);
            }

            if ($currentVersion !== null && blank($validated['motivo_cambio'])) {
                throw ValidationException::withMessages([
                    'motivo_cambio' => 'Explica el motivo del cambio de precio.',
                ]);
            }

            $effectiveAt = now()->startOfSecond();

            if ($currentVersion?->vigente_desde?->greaterThanOrEqualTo($effectiveAt)) {
                $effectiveAt = $currentVersion->vigente_desde->copy()->addSecond();
            }

            if ($effectiveAt->toDateString() !== $validated['fecha']) {
                throw ValidationException::withMessages([
                    'precio_kg' => 'No fue posible registrar otra versión en esta fecha.',
                ]);
            }

            $priceDay->versiones()->create([
                'precio_kg' => $validated['precio_kg'],
                'vigente_desde' => $effectiveAt,
                'registrado_por' => $user->getKey(),
                'motivo_cambio' => $validated['motivo_cambio'],
            ]);

            return $priceDay;
        }, 3);

        return to_route('precios-dia.show', $priceDay)
            ->with('status', 'Precio del día registrado correctamente.');
    }

    public function show(PrecioDia $precioDia): View
    {
        $precioDia->load([
            'producto:id,nombre,activo',
            'versiones' => fn ($query) => $query
                ->select(['id', 'precio_dia_id', 'precio_kg', 'vigente_desde', 'registrado_por', 'motivo_cambio'])
                ->with('registradoPor:id,nombres,apellidos,usuario')
                ->orderByDesc('vigente_desde')
                ->orderByDesc('id'),
        ]);

        return view('precios-dia.show', [
            'priceDay' => $precioDia,
        ]);
    }

    private function filterDate(Request $request): string
    {
        $date = $request->string('fecha')->toString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return today()->toDateString();
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year)
            ? $date
            : today()->toDateString();
    }
}
