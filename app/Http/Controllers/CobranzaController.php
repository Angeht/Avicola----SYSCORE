<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCobranzaRequest;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\MedioPago;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CobranzaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $search = $request->string('buscar')->trim()->toString();
        $status = in_array($request->string('estado')->toString(), ['vigentes', 'anuladas', 'todas'], true)
            ? $request->string('estado')->toString()
            : 'vigentes';
        $type = in_array($request->string('tipo')->toString(), ['PAGO_VENTA', 'ABONO'], true)
            ? $request->string('tipo')->toString()
            : null;
        $date = $this->operationalDateFilter($request);

        $collections = Cobranza::query()
            ->select(['id', 'numero_cobranza', 'cliente_id', 'usuario_id', 'sesion_caja_id', 'medio_pago_id', 'tipo', 'monto_total', 'fecha_pago', 'anulada_por', 'anulada_at'])
            ->with([
                'cliente:id,nombres_razon_social,nro_documento',
                'usuario:id,nombres,apellidos,usuario',
                'medioPago:id,codigo,nombre,es_efectivo',
                'anuladaPor:id,nombres,apellidos,usuario',
            ])
            ->withCount('aplicaciones')
            ->withSum('aplicaciones as monto_aplicado', 'monto_aplicado')
            ->search($search)
            ->when($status === 'vigentes', fn ($query) => $query->whereNull('anulada_at'))
            ->when($status === 'anuladas', fn ($query) => $query->whereNotNull('anulada_at'))
            ->when($type !== null, fn ($query) => $query->where('tipo', $type))
            ->when($date !== null, fn ($query) => $query->whereDate('fecha_pago', $date))
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $todaySummary = DB::table('cobranzas as c')
            ->join('medios_pago as mp', 'mp.id', '=', 'c.medio_pago_id')
            ->whereDate('c.fecha_pago', today()->toDateString())
            ->selectRaw('SUM(CASE WHEN c.anulada_at IS NULL THEN 1 ELSE 0 END) AS cantidad_cobranzas')
            ->selectRaw('COALESCE(SUM(CASE WHEN c.anulada_at IS NULL THEN c.monto_total ELSE 0 END), 0) AS total_cobrado')
            ->selectRaw('COALESCE(SUM(CASE WHEN c.anulada_at IS NULL AND mp.es_efectivo = 1 THEN c.monto_total ELSE 0 END), 0) AS total_efectivo')
            ->selectRaw('COALESCE(SUM(CASE WHEN c.anulada_at IS NULL AND mp.es_efectivo = 0 THEN c.monto_total ELSE 0 END), 0) AS total_otros_medios')
            ->selectRaw('SUM(CASE WHEN c.anulada_at IS NOT NULL THEN 1 ELSE 0 END) AS cantidad_anuladas')
            ->first();

        return view('cobranzas.index', [
            'authenticatedUser' => $user,
            'collections' => $collections,
            'date' => $date,
            'search' => $search,
            'status' => $status,
            'todaySummary' => $todaySummary,
            'type' => $type,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $sales = DB::table('vw_totales_venta as v')
            ->selectRaw('v.cliente_id, COUNT(*) as cantidad_ventas, COALESCE(SUM(v.total_venta), 0) as total_ventas')
            ->whereNotNull('v.cliente_id')
            ->where('v.estado', 'ACTIVA')
            ->groupBy('v.cliente_id');
        $collections = DB::table('cobranzas as co')
            ->selectRaw('co.cliente_id, COALESCE(SUM(co.monto_total), 0) as total_cobrado')
            ->whereNotNull('co.cliente_id')
            ->whereNull('co.anulada_at')
            ->groupBy('co.cliente_id');
        $clients = DB::table('clientes as cl')
            ->joinSub($sales, 'v', fn (JoinClause $join): JoinClause => $join->on('v.cliente_id', '=', 'cl.id'))
            ->leftJoinSub($collections, 'co', fn (JoinClause $join): JoinClause => $join->on('co.cliente_id', '=', 'cl.id'))
            ->leftJoin('vw_saldos_cliente as sc', 'sc.cliente_id', '=', 'cl.id')
            ->where('cl.activo', true)
            ->whereRaw('COALESCE(v.total_ventas, 0) - COALESCE(co.total_cobrado, 0) > 0')
            ->select([
                'cl.id',
                'cl.nombres_razon_social',
                'cl.nro_documento',
                DB::raw('ROUND(COALESCE(v.total_ventas, 0) - COALESCE(co.total_cobrado, 0), 2) as deuda_total'),
                DB::raw('COALESCE(sc.ventas_pendientes, 0) as ventas_pendientes'),
            ])
            ->orderBy('cl.nombres_razon_social')
            ->get();
        $openCashSession = $this->openCashSession($user);
        $requestedClientId = Venta::query()
            ->whereKey($request->integer('venta'))
            ->whereNull('anulada_at')
            ->value('cliente_id');
        $preselectedClientId = $clients->contains('id', $requestedClientId)
            ? (int) $requestedClientId
            : null;

        return view('cobranzas.create', [
            'authenticatedUser' => $user,
            'clients' => $clients,
            'openCashSession' => $openCashSession,
            'openCashSummary' => $openCashSession === null
                ? null
                : DB::table('vw_resumen_caja_usuario')->where('sesion_caja_id', $openCashSession->getKey())->first(),
            'paymentMethods' => MedioPago::query()
                ->select(['id', 'codigo', 'nombre', 'es_efectivo'])
                ->where('activo', true)
                ->orderByDesc('es_efectivo')
                ->orderBy('nombre')
                ->get(),
            'preselectedClientId' => $preselectedClientId,
        ]);
    }

    public function store(StoreCobranzaRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $collection = DB::transaction(function () use ($user, $validated): Cobranza {
            $client = Cliente::query()
                ->whereKey($validated['cliente_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->first();

            if ($client === null) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'El cliente seleccionado ya no está disponible.',
                ]);
            }

            $paymentMethod = MedioPago::query()
                ->whereKey($validated['medio_pago_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSaleIds = Venta::query()
                ->where('cliente_id', $client->getKey())
                ->whereNull('anulada_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $balances = DB::table('vw_saldos_venta')
                ->whereIn('venta_id', $lockedSaleIds)
                ->where('saldo_pendiente', '>', 0)
                ->where('estado_pago', '<>', 'ANULADA')
                ->orderBy('fecha_venta')
                ->orderBy('venta_id')
                ->get(['venta_id', 'saldo_pendiente']);
            $salesTotalCents = $this->moneyToCents(DB::table('vw_totales_venta')
                ->where('cliente_id', $client->getKey())
                ->where('estado', 'ACTIVA')
                ->sum('total_venta'));
            $collectedTotalCents = $this->moneyToCents(Cobranza::query()
                ->where('cliente_id', $client->getKey())
                ->whereNull('anulada_at')
                ->sum('monto_total'));
            $debtCents = max(0, $salesTotalCents - $collectedTotalCents);
            $receivedCents = $this->moneyToCents($validated['monto_total']);

            if ($debtCents === 0) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'El cliente ya no tiene deuda pendiente.',
                ]);
            }

            if ($receivedCents > $debtCents) {
                throw ValidationException::withMessages([
                    'monto_total' => 'El monto recibido no puede superar la deuda actual del cliente.',
                ]);
            }

            $openCashSession = SesionCaja::query()
                ->where('usuario_id', $user->getKey())
                ->where('fecha_operacion', today()->toDateString())
                ->abiertas()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($paymentMethod->es_efectivo && $openCashSession === null) {
                throw ValidationException::withMessages([
                    'medio_pago_id' => 'Abre una sesión de caja antes de registrar una cobranza en efectivo.',
                ]);
            }

            $collection = Cobranza::query()->create([
                'numero_cobranza' => 'TMP-'.Str::ulid(),
                'cliente_id' => $client->getKey(),
                'usuario_id' => $user->getKey(),
                'sesion_caja_id' => $openCashSession?->getKey(),
                'medio_pago_id' => $paymentMethod->getKey(),
                'tipo' => 'PAGO_VENTA',
                'monto_total' => $receivedCents / 100,
                'fecha_pago' => now(),
                'observacion' => $validated['observacion'],
            ]);

            $collection->update([
                'numero_cobranza' => sprintf('COB-%s-%06d', now()->format('Ymd'), $collection->getKey()),
            ]);

            $remainingCents = $receivedCents;

            foreach ($balances as $balance) {
                if ($remainingCents === 0) {
                    break;
                }

                $appliedCents = min($remainingCents, $this->moneyToCents($balance->saldo_pendiente));

                $collection->aplicaciones()->create([
                    'venta_id' => $balance->venta_id,
                    'monto_aplicado' => $appliedCents / 100,
                ]);

                $remainingCents -= $appliedCents;
            }

            if ($remainingCents > 0) {
                throw ValidationException::withMessages([
                    'monto_total' => 'No fue posible aplicar todo el pago a las ventas pendientes del cliente.',
                ]);
            }

            return $collection;
        }, 3);

        return to_route('cobranzas.show', $collection)
            ->with('status', "Cobranza {$collection->numero_cobranza} registrada correctamente.");
    }

    public function show(Request $request, Cobranza $cobranza): View
    {
        $cobranza->load([
            'cliente:id,nombres_razon_social,nro_documento,telefono,direccion',
            'usuario:id,nombres,apellidos,usuario',
            'sesionCaja:id,usuario_id,fecha_operacion,apertura_at,cierre_at',
            'medioPago:id,codigo,nombre,es_efectivo',
            'anuladaPor:id,nombres,apellidos,usuario',
            'aplicaciones' => fn ($query) => $query
                ->select(['cobranza_id', 'venta_id', 'monto_aplicado', 'created_at'])
                ->with('venta:id,numero_venta,cliente_id,fecha_venta,anulada_at')
                ->orderBy('venta_id'),
            'aplicaciones.venta.cliente:id,nombres_razon_social,nro_documento',
        ]);
        $saleIds = $cobranza->aplicaciones->pluck('venta_id');
        $appliedCents = $cobranza->aplicaciones->sum(
            fn ($application): int => $this->moneyToCents($application->monto_aplicado),
        );

        return view('cobranzas.show', [
            'appliedAmount' => $appliedCents / 100,
            'authenticatedUser' => $this->authenticatedUser($request),
            'cashSummary' => $cobranza->sesion_caja_id === null
                ? null
                : DB::table('vw_resumen_caja_usuario')
                    ->where('sesion_caja_id', $cobranza->sesion_caja_id)
                    ->first(),
            'collection' => $cobranza,
            'saleBalances' => DB::table('vw_saldos_venta')
                ->whereIn('venta_id', $saleIds)
                ->get()
                ->keyBy('venta_id'),
            'unappliedAmount' => max(0, $this->moneyToCents($cobranza->monto_total) - $appliedCents) / 100,
        ]);
    }

    private function authenticatedUser(Request $request): Usuario
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        return $user;
    }

    private function openCashSession(Usuario $user): ?SesionCaja
    {
        return SesionCaja::query()
            ->where('usuario_id', $user->getKey())
            ->where('fecha_operacion', today()->toDateString())
            ->abiertas()
            ->orderByDesc('id')
            ->first();
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
