<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTipoJabaRequest;
use App\Http\Requests\UpdateTipoJabaRequest;
use App\Models\TipoJaba;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TipoJabaController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->status($request);
        $search = $request->string('buscar')->trim()->toString();

        $crateTypes = TipoJaba::query()
            ->select(['id', 'nombre', 'tara_referencial_kg', 'descripcion', 'activo', 'updated_at'])
            ->search($search)
            ->when($status !== 'todos', fn (Builder $query): Builder => $query->where('activo', $status === 'activos'))
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('tipos-jaba.index', [
            'activeCount' => TipoJaba::query()->where('activo', true)->count(),
            'inactiveCount' => TipoJaba::query()->where('activo', false)->count(),
            'pendingTareCount' => TipoJaba::query()->where('activo', true)->where('tara_referencial_kg', '<=', 0)->count(),
            'averageTare' => TipoJaba::query()->where('activo', true)->where('tara_referencial_kg', '>', 0)->avg('tara_referencial_kg'),
            'crateTypes' => $crateTypes,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('tipos-jaba.create');
    }

    public function store(StoreTipoJabaRequest $request): RedirectResponse
    {
        TipoJaba::query()->create([
            ...$request->validated(),
            'activo' => true,
        ]);

        return to_route('tipos-jaba.index')
            ->with('status', 'Tipo de jaba registrado correctamente.');
    }

    public function edit(TipoJaba $tipoJaba): View
    {
        return view('tipos-jaba.edit', [
            'crateType' => $tipoJaba,
        ]);
    }

    public function update(UpdateTipoJabaRequest $request, TipoJaba $tipoJaba): RedirectResponse
    {
        $tipoJaba->update($request->validated());

        return to_route('tipos-jaba.index')
            ->with('status', 'Tipo de jaba actualizado correctamente.');
    }

    private function status(Request $request): string
    {
        $status = $request->string('estado')->toString();

        return in_array($status, ['todos', 'activos', 'inactivos'], true)
            ? $status
            : 'activos';
    }
}
