<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCargaProveedorRequest;
use App\Models\CargaProveedor;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CargaProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('buscar')->trim()->toString();
        $date = $this->operationalDateFilter($request);

        $loads = CargaProveedor::query()
            ->select(['id', 'numero_carga', 'proveedor_id', 'producto_id', 'fecha_carga', 'costo_kg', 'costo_total', 'recibido_por', 'anulada_por', 'anulada_at'])
            ->with([
                'proveedor:id,nombre_razon_social',
                'producto:id,nombre',
                'recibidoPor:id,nombres,apellidos,usuario',
                'anuladaPor:id,nombres,apellidos,usuario',
            ])
            ->withCount('pesajes')
            ->search($search)
            ->when($date !== null, fn ($query) => $query->whereDate('fecha_carga', $date))
            ->orderByDesc('fecha_carga')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $loadIds = $loads->getCollection()->pluck('id');
        $summaries = DB::table('vw_resumen_carga')
            ->whereIn('carga_id', $loadIds)
            ->get()
            ->keyBy('carga_id');
        $balances = DB::table('vw_saldos_carga_proveedor')
            ->whereIn('carga_id', $loadIds)
            ->get()
            ->keyBy('carga_id');
        $todaySummary = DB::table('vw_resumen_carga')
            ->where('fecha_carga', today()->toDateString())
            ->whereNull('anulada_at')
            ->selectRaw('COUNT(*) AS cantidad_cargas, COALESCE(SUM(cantidad_pollos), 0) AS cantidad_pollos, COALESCE(SUM(peso_neto_kg), 0) AS peso_neto_kg, COALESCE(SUM(costo_total), 0) AS costo_total')
            ->first();

        return view('cargas-proveedor.index', [
            'balances' => $balances,
            'date' => $date,
            'loads' => $loads,
            'search' => $search,
            'summaries' => $summaries,
            'todaySummary' => $todaySummary,
        ]);
    }

    public function create(): View
    {
        return view('cargas-proveedor.create', [
            'products' => Producto::query()
                ->select(['id', 'nombre'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'providers' => Proveedor::query()
                ->select(['id', 'nombre_razon_social', 'nro_documento'])
                ->where('activo', true)
                ->orderBy('nombre_razon_social')
                ->get(),
        ]);
    }

    public function store(StoreCargaProveedorRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        $validated = $request->validated();

        $load = DB::transaction(function () use ($user, $validated): CargaProveedor {
            Proveedor::query()
                ->whereKey($validated['proveedor_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            Producto::query()
                ->whereKey($validated['producto_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();

            $load = CargaProveedor::query()->create([
                'numero_carga' => 'TMP-'.Str::ulid(),
                'proveedor_id' => $validated['proveedor_id'],
                'producto_id' => $validated['producto_id'],
                'fecha_carga' => $validated['fecha_carga'],
                'costo_kg' => $validated['costo_kg'],
                'costo_total' => 0,
                'recibido_por' => $user->getKey(),
                'observacion' => $validated['observacion'],
            ]);

            $load->update([
                'numero_carga' => sprintf(
                    'CAR-%s-%06d',
                    str_replace('-', '', $validated['fecha_carga']),
                    $load->getKey(),
                ),
            ]);

            return $load;
        }, 3);

        return to_route('cargas-proveedor.pesajes.create', $load)
            ->with('status', "Carga {$load->numero_carga} creada. Ahora registra sus pesajes.");
    }

    public function show(Request $request, CargaProveedor $cargaProveedor): View
    {
        $cargaProveedor->load([
            'proveedor:id,nombre_razon_social,nro_documento,telefono,direccion',
            'producto:id,nombre,unidad_medida_id',
            'producto.unidadMedida:id,codigo,nombre,simbolo',
            'recibidoPor:id,nombres,apellidos,usuario',
            'anuladaPor:id,nombres,apellidos,usuario',
            'pesajes' => fn ($query) => $query
                ->select(['id', 'carga_id', 'tipo_jaba_id', 'cantidad_jabas', 'cantidad_pollos', 'peso_bruto_kg', 'tara_unitaria_aplicada_kg', 'observacion', 'editado_por', 'autorizado_por', 'editado_at', 'created_at'])
                ->with([
                    'tipoJaba:id,nombre',
                    'editadoPor:id,nombres,apellidos,usuario',
                    'autorizadoPor:id,nombres,apellidos,usuario',
                ])
                ->orderBy('id'),
            'pagosProveedor' => fn ($query) => $query
                ->select(['id', 'numero_pago', 'carga_id', 'medio_pago_id', 'monto', 'pagado_por', 'pagado_at', 'anulada_por', 'anulada_at', 'motivo_anulacion'])
                ->with([
                    'medioPago:id,nombre,es_efectivo',
                    'pagadoPor:id,nombres,apellidos,usuario',
                    'anuladaPor:id,nombres,apellidos,usuario',
                ])
                ->orderByDesc('pagado_at')
                ->orderByDesc('id'),
        ]);

        return view('cargas-proveedor.show', [
            'authenticatedUser' => $request->user(),
            'balance' => DB::table('vw_saldos_carga_proveedor')
                ->where('carga_id', $cargaProveedor->getKey())
                ->firstOrFail(),
            'load' => $cargaProveedor,
            'hasActivePayments' => $cargaProveedor->pagosProveedor->contains(
                fn ($payment): bool => ! $payment->estaAnulado(),
            ),
            'summary' => DB::table('vw_resumen_carga')
                ->where('carga_id', $cargaProveedor->getKey())
                ->firstOrFail(),
        ]);
    }
}
