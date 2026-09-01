<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditFiltersRequest;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class AuditoriaController extends Controller
{
    public function index(AuditFiltersRequest $request): View
    {
        $filters = $request->safe()->all();

        if (! $request->hasAny(['desde', 'hasta'])) {
            $filters['desde'] = today()->toDateString();
            $filters['hasta'] = today()->toDateString();
        }

        $query = Auditoria::query()
            ->select(['id', 'usuario_id', 'tabla_afectada', 'registro_id', 'accion', 'ip', 'created_at'])
            ->with('usuario:id,nombres,apellidos,usuario')
            ->withCount('detalles')
            ->when($filters['buscar'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('tabla_afectada', 'like', "%{$search}%")
                        ->orWhere('registro_id', $search)
                        ->orWhereHas('usuario', function (Builder $query) use ($search): void {
                            $query->where('nombres', 'like', "%{$search}%")
                                ->orWhere('apellidos', 'like', "%{$search}%")
                                ->orWhere('usuario', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['accion'] ?? null, fn (Builder $query, string $action): Builder => $query->where('accion', $action))
            ->when($filters['tabla'] ?? null, fn (Builder $query, string $table): Builder => $query->where('tabla_afectada', $table))
            ->when($filters['usuario_id'] ?? null, fn (Builder $query, int|string $userId): Builder => $query->where('usuario_id', $userId))
            ->when($filters['desde'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['hasta'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));

        $audits = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('auditorias.index', [
            'actions' => [
                'INSERT' => 'Creación',
                'UPDATE' => 'Modificación',
                'DELETE' => 'Eliminación',
                'ANULAR' => 'Anulación',
                'LOGIN' => 'Acceso',
                'OTRO' => 'Otro cambio',
            ],
            'annulmentsToday' => Auditoria::query()->where('accion', 'ANULAR')->whereDate('created_at', today())->count(),
            'audits' => $audits,
            'changesToday' => Auditoria::query()->whereDate('created_at', today())->count(),
            'filters' => $filters,
            'tables' => Auditoria::query()->distinct()->orderBy('tabla_afectada')->pluck('tabla_afectada'),
            'users' => Usuario::query()
                ->select(['id', 'nombres', 'apellidos', 'usuario'])
                ->whereIn('id', Auditoria::query()->select('usuario_id')->whereNotNull('usuario_id'))
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->get(),
        ]);
    }

    public function show(Auditoria $auditoria): View
    {
        $auditoria->load([
            'detalles' => fn ($query) => $query->orderBy('id'),
            'usuario:id,nombres,apellidos,usuario',
        ]);

        return view('auditorias.show', [
            'audit' => $auditoria,
        ]);
    }
}
