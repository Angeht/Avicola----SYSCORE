<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->status($request);
        $search = $request->string('buscar')->trim()->toString();

        $suppliers = Proveedor::query()
            ->select([
                'id',
                'tipo_documento_id',
                'nro_documento',
                'nombre_razon_social',
                'telefono',
                'direccion',
                'activo',
                'updated_at',
            ])
            ->with('tipoDocumento:id,codigo,nombre')
            ->search($search)
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('activo', $status === 'activos'))
            ->orderBy('nombre_razon_social')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('proveedores.index', [
            'activeCount' => Proveedor::query()->where('activo', true)->count(),
            'inactiveCount' => Proveedor::query()->where('activo', false)->count(),
            'search' => $search,
            'status' => $status,
            'suppliers' => $suppliers,
        ]);
    }

    public function create(): View
    {
        return view('proveedores.create', [
            'documentTypes' => TipoDocumento::query()
                ->select(['id', 'codigo', 'nombre', 'longitud_maxima'])
                ->where('activo', true)
                ->orderBy('codigo')
                ->get(),
        ]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        Proveedor::query()->create($request->validated());

        return to_route('proveedores.index')
            ->with('status', 'Proveedor registrado correctamente.');
    }

    public function edit(Proveedor $proveedor): View
    {
        return view('proveedores.edit', [
            'documentTypes' => TipoDocumento::query()
                ->select(['id', 'codigo', 'nombre', 'longitud_maxima'])
                ->where(function (Builder $query) use ($proveedor): void {
                    $query->where('activo', true)
                        ->when(
                            $proveedor->tipo_documento_id !== null,
                            fn (Builder $query): Builder => $query->orWhereKey($proveedor->tipo_documento_id),
                        );
                })
                ->orderBy('codigo')
                ->get(),
            'supplier' => $proveedor,
        ]);
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($request->validated());

        return to_route('proveedores.index')
            ->with('status', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update(['activo' => false]);

        return to_route('proveedores.index')
            ->with('status', 'Proveedor desactivado. Su historial se mantiene disponible.');
    }

    private function status(Request $request): string
    {
        $status = $request->string('estado')->toString();

        return in_array($status, ['todos', 'activos', 'inactivos'], true)
            ? $status
            : 'activos';
    }
}
