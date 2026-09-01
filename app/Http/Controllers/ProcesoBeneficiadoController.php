<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcesoBeneficiadoRequest;
use App\Models\CargaProveedor;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcesoBeneficiadoController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('buscar')->trim()->toString();
        $status = in_array($request->string('estado')->toString(), ['vigentes', 'anulados', 'todos'], true)
            ? $request->string('estado')->toString()
            : 'vigentes';
        $date = $this->operationalDateFilter($request);
        $processes = ProcesoBeneficiado::query()
            ->select(['id', 'numero_proceso', 'carga_proveedor_id', 'producto_destino_id', 'cantidad_pollos', 'peso_origen_kg', 'peso_resultante_kg', 'procesado_at', 'procesado_por', 'anulado_at'])
            ->with([
                'cargaProveedor:id,numero_carga,proveedor_id,producto_id,fecha_carga',
                'cargaProveedor.proveedor:id,nombre_razon_social',
                'cargaProveedor.producto:id,nombre',
                'productoDestino:id,nombre',
                'procesadoPor:id,nombres,apellidos,usuario',
            ])
            ->search($search)
            ->when($status === 'vigentes', fn ($query) => $query->whereNull('anulado_at'))
            ->when($status === 'anulados', fn ($query) => $query->whereNotNull('anulado_at'))
            ->when($date !== null, fn ($query) => $query->whereDate('procesado_at', $date))
            ->orderByDesc('procesado_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
        $todaySummary = ProcesoBeneficiado::query()
            ->vigentes()
            ->whereDate('procesado_at', today()->toDateString())
            ->selectRaw('COUNT(*) AS cantidad_procesos')
            ->selectRaw('COALESCE(SUM(cantidad_pollos), 0) AS pollos_procesados')
            ->selectRaw('COALESCE(SUM(peso_origen_kg), 0) AS kg_origen')
            ->selectRaw('COALESCE(SUM(peso_resultante_kg), 0) AS kg_resultantes')
            ->first();

        return view('beneficiados.index', [
            'authenticatedUser' => $this->authenticatedUser($request),
            'date' => $date,
            'processes' => $processes,
            'search' => $search,
            'status' => $status,
            'todaySummary' => $todaySummary,
        ]);
    }

    public function create(Request $request): View
    {
        $loads = $this->availableLoads();
        $preselectedLoadId = $loads->contains('id', $request->integer('carga'))
            ? $request->integer('carga')
            : null;

        return view('beneficiados.create', [
            'authenticatedUser' => $this->authenticatedUser($request),
            'destinationProducts' => Producto::query()
                ->select(['id', 'nombre'])
                ->where('activo', true)
                ->where('modalidad_venta', Producto::MODALIDAD_SOLO_PESO)
                ->orderBy('nombre')
                ->get(),
            'loads' => $loads,
            'preselectedLoadId' => $preselectedLoadId,
        ]);
    }

    public function store(StoreProcesoBeneficiadoRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $process = DB::transaction(function () use ($user, $validated): ProcesoBeneficiado {
            $load = CargaProveedor::query()
                ->whereKey($validated['carga_proveedor_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $products = Producto::query()
                ->whereKey([$load->producto_id, $validated['producto_destino_id']])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $sourceProduct = $products->get($load->producto_id);
            $destinationProduct = $products->get($validated['producto_destino_id']);

            if ($load->estaAnulada() || ! $sourceProduct instanceof Producto || ! $sourceProduct->activo
                || $sourceProduct->modalidad_venta !== Producto::MODALIDAD_PESAJE_VIVO) {
                throw ValidationException::withMessages([
                    'carga_proveedor_id' => 'La carga ya no está disponible para beneficiado.',
                ]);
            }

            if (! $destinationProduct instanceof Producto || ! $destinationProduct->activo
                || ! $destinationProduct->seVendeSoloPorPeso()) {
                throw ValidationException::withMessages([
                    'producto_destino_id' => 'El producto beneficiado ya no está disponible.',
                ]);
            }

            $previousProcesses = ProcesoBeneficiado::query()
                ->vigentes()
                ->where('carga_proveedor_id', $load->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'cantidad_pollos', 'peso_origen_kg']);
            $summary = DB::table('vw_resumen_carga')
                ->where('carga_id', $load->getKey())
                ->firstOrFail();
            $stock = DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $sourceProduct->getKey())
                ->firstOrFail();

            $availableBirds = min(
                max(0, (int) $summary->cantidad_pollos - (int) $previousProcesses->sum('cantidad_pollos')),
                max(0, (int) $stock->pollos_disponibles),
            );
            $availableWeightGrams = min(
                max(0, $this->kilogramsToGrams($summary->peso_neto_kg) - $this->kilogramsToGrams($previousProcesses->sum('peso_origen_kg'))),
                max(0, $this->kilogramsToGrams($stock->kg_disponibles)),
            );

            if ((int) $validated['cantidad_pollos'] > $availableBirds) {
                throw ValidationException::withMessages([
                    'cantidad_pollos' => 'La cantidad supera las aves disponibles de la carga y del stock vivo.',
                ]);
            }

            if ($this->kilogramsToGrams($validated['peso_origen_kg']) > $availableWeightGrams) {
                throw ValidationException::withMessages([
                    'peso_origen_kg' => 'El peso supera los kilogramos disponibles de la carga y del stock vivo.',
                ]);
            }

            $process = ProcesoBeneficiado::query()->create([
                'numero_proceso' => 'TMP-'.Str::ulid(),
                'carga_proveedor_id' => $load->getKey(),
                'producto_destino_id' => $destinationProduct->getKey(),
                'cantidad_pollos' => $validated['cantidad_pollos'],
                'peso_origen_kg' => $validated['peso_origen_kg'],
                'peso_resultante_kg' => $validated['peso_resultante_kg'],
                'procesado_at' => now(),
                'procesado_por' => $user->getKey(),
                'observacion' => $validated['observacion'],
            ]);

            $process->update([
                'numero_proceso' => sprintf('BEN-%s-%06d', now()->format('Ymd'), $process->getKey()),
            ]);

            return $process;
        }, 3);

        return to_route('beneficiados.show', $process)
            ->with('status', "Beneficiado {$process->numero_proceso} registrado correctamente.");
    }

    public function show(Request $request, ProcesoBeneficiado $procesoBeneficiado): View
    {
        $procesoBeneficiado->load([
            'cargaProveedor:id,numero_carga,proveedor_id,producto_id,fecha_carga,anulada_at',
            'cargaProveedor.proveedor:id,nombre_razon_social,nro_documento',
            'cargaProveedor.producto:id,nombre,modalidad_venta',
            'productoDestino:id,nombre,modalidad_venta',
            'procesadoPor:id,nombres,apellidos,usuario',
            'anuladoPor:id,nombres,apellidos,usuario',
        ]);
        $blockReason = $this->cancellationBlockReason($procesoBeneficiado);

        return view('beneficiados.show', [
            'authenticatedUser' => $this->authenticatedUser($request),
            'canCancel' => ! $procesoBeneficiado->estaAnulado() && $blockReason === null,
            'cancellationBlockReason' => $blockReason,
            'destinationBalance' => DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $procesoBeneficiado->producto_destino_id)
                ->firstOrFail(),
            'process' => $procesoBeneficiado,
            'sourceBalance' => DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $procesoBeneficiado->cargaProveedor->producto_id)
                ->firstOrFail(),
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function availableLoads(): Collection
    {
        $processedByLoad = DB::table('procesos_beneficiado')
            ->whereNull('anulado_at')
            ->select('carga_proveedor_id')
            ->selectRaw('SUM(cantidad_pollos) AS pollos_procesados')
            ->selectRaw('SUM(peso_origen_kg) AS kg_procesados')
            ->groupBy('carga_proveedor_id');

        return DB::table('cargas_proveedor as cp')
            ->join('vw_resumen_carga as rc', 'rc.carga_id', '=', 'cp.id')
            ->join('productos as p', 'p.id', '=', 'cp.producto_id')
            ->join('proveedores as pr', 'pr.id', '=', 'cp.proveedor_id')
            ->join('vw_saldo_mercaderia_actual as sm', 'sm.producto_id', '=', 'cp.producto_id')
            ->leftJoinSub($processedByLoad, 'pb', 'pb.carga_proveedor_id', '=', 'cp.id')
            ->whereNull('cp.anulada_at')
            ->where('p.activo', true)
            ->where('p.modalidad_venta', Producto::MODALIDAD_PESAJE_VIVO)
            ->where('rc.cantidad_pollos', '>', 0)
            ->where('rc.peso_neto_kg', '>', 0)
            ->select([
                'cp.id',
                'cp.numero_carga',
                'cp.fecha_carga',
                'p.id as producto_id',
                'p.nombre as producto',
                'pr.nombre_razon_social as proveedor',
                'rc.cantidad_pollos as pollos_carga',
                'rc.peso_neto_kg as kg_carga',
                'sm.pollos_disponibles as pollos_stock',
                'sm.kg_disponibles as kg_stock',
                DB::raw('COALESCE(pb.pollos_procesados, 0) as pollos_procesados'),
                DB::raw('COALESCE(pb.kg_procesados, 0) as kg_procesados'),
            ])
            ->orderByDesc('cp.fecha_carga')
            ->orderByDesc('cp.id')
            ->get()
            ->map(function (object $load): object {
                $load->pollos_disponibles = min(
                    max(0, (int) $load->pollos_carga - (int) $load->pollos_procesados),
                    max(0, (int) $load->pollos_stock),
                );
                $load->kg_disponibles = min(
                    max(0, (float) $load->kg_carga - (float) $load->kg_procesados),
                    max(0, (float) $load->kg_stock),
                );

                return $load;
            })
            ->filter(fn (object $load): bool => $load->pollos_disponibles > 0 && $load->kg_disponibles > 0)
            ->values();
    }

    private function cancellationBlockReason(ProcesoBeneficiado $process): ?string
    {
        if ($process->estaAnulado()) {
            return null;
        }

        $destinationStock = DB::table('vw_saldo_mercaderia_actual')
            ->where('producto_id', $process->producto_destino_id)
            ->first();

        if ($destinationStock !== null
            && $this->kilogramsToGrams($process->peso_resultante_kg) > $this->kilogramsToGrams($destinationStock->kg_disponibles)) {
            return 'Parte del producto beneficiado ya fue utilizada y el proceso no puede anularse.';
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
