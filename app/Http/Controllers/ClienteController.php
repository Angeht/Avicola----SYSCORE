<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\TipoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->status($request);
        $search = $request->string('buscar')->trim()->toString();

        $clients = Cliente::query()
            ->select([
                'id',
                'tipo_documento_id',
                'nro_documento',
                'nombres_razon_social',
                'telefono',
                'direccion',
                'observacion',
                'activo',
                'updated_at',
            ])
            ->with('tipoDocumento:id,codigo,nombre')
            ->search($search)
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('activo', $status === 'activos'))
            ->orderBy('nombres_razon_social')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('clientes.index', [
            'activeCount' => Cliente::query()->where('activo', true)->count(),
            'clients' => $clients,
            'inactiveCount' => Cliente::query()->where('activo', false)->count(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', [
            'documentTypes' => TipoDocumento::query()
                ->select(['id', 'codigo', 'nombre', 'longitud_maxima'])
                ->where('activo', true)
                ->orderBy('codigo')
                ->get(),
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        Cliente::query()->create($request->validated());

        return to_route('clientes.index')
            ->with('status', 'Cliente registrado correctamente.');
    }

    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', [
            'client' => $cliente,
            'documentTypes' => TipoDocumento::query()
                ->select(['id', 'codigo', 'nombre', 'longitud_maxima'])
                ->where(function (Builder $query) use ($cliente): void {
                    $query->where('activo', true)
                        ->when(
                            $cliente->tipo_documento_id !== null,
                            fn (Builder $query): Builder => $query->orWhereKey($cliente->tipo_documento_id),
                        );
                })
                ->orderBy('codigo')
                ->get(),
        ]);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return to_route('clientes.index')
            ->with('status', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->update(['activo' => false]);

        return to_route('clientes.index')
            ->with('status', 'Cliente desactivado. Su historial se mantiene disponible.');
    }

    private function status(Request $request): string
    {
        $status = $request->string('estado')->toString();

        return in_array($status, ['todos', 'activos', 'inactivos'], true)
            ? $status
            : 'activos';
    }
}
