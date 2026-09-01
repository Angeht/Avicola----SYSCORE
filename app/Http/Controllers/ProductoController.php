<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->status($request);
        $search = $request->string('buscar')->trim()->toString();

        $products = Producto::query()
            ->select(['id', 'nombre', 'descripcion', 'unidad_medida_id', 'modalidad_venta', 'activo', 'updated_at'])
            ->with('unidadMedida:id,codigo,nombre,simbolo')
            ->search($search)
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('activo', $status === 'activos'))
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('productos.index', [
            'activeCount' => Producto::query()->where('activo', true)->count(),
            'inactiveCount' => Producto::query()->where('activo', false)->count(),
            'products' => $products,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('productos.create', [
            'measurementUnits' => UnidadMedida::query()
                ->select(['id', 'codigo', 'nombre', 'simbolo'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'saleModes' => Producto::modalidadesVenta(),
        ]);
    }

    public function store(StoreProductoRequest $request): RedirectResponse
    {
        Producto::query()->create($request->validated());

        return to_route('productos.index')
            ->with('status', 'Producto registrado correctamente.');
    }

    public function edit(Producto $producto): View
    {
        return view('productos.edit', [
            'measurementUnits' => UnidadMedida::query()
                ->select(['id', 'codigo', 'nombre', 'simbolo'])
                ->where(function (Builder $query) use ($producto): void {
                    $query->where('activo', true)
                        ->orWhereKey($producto->unidad_medida_id);
                })
                ->orderBy('nombre')
                ->get(),
            'product' => $producto,
            'saleModes' => Producto::modalidadesVenta(),
        ]);
    }

    public function update(UpdateProductoRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update($request->validated());

        return to_route('productos.index')
            ->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->update(['activo' => false]);

        return to_route('productos.index')
            ->with('status', 'Producto desactivado. Su historial se mantiene disponible.');
    }

    private function status(Request $request): string
    {
        $status = $request->string('estado')->toString();

        return in_array($status, ['todos', 'activos', 'inactivos'], true)
            ? $status
            : 'activos';
    }
}
