<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagoProveedorRequest;
use App\Models\CargaProveedor;
use App\Models\MedioPago;
use App\Models\PagoProveedor;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PagoProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $search = $request->string('buscar')->trim()->toString();
        $status = in_array($request->string('estado')->toString(), ['vigentes', 'anulados', 'todos'], true)
            ? $request->string('estado')->toString()
            : 'vigentes';
        $date = $this->operationalDateFilter($request);

        $payments = PagoProveedor::query()
            ->select(['id', 'numero_pago', 'carga_id', 'sesion_caja_id', 'medio_pago_id', 'monto', 'pagado_por', 'pagado_at', 'anulada_por', 'anulada_at'])
            ->with([
                'carga:id,numero_carga,proveedor_id,costo_total,fecha_carga',
                'carga.proveedor:id,nombre_razon_social',
                'medioPago:id,codigo,nombre,es_efectivo',
                'pagadoPor:id,nombres,apellidos,usuario',
                'anuladaPor:id,nombres,apellidos,usuario',
            ])
            ->search($search)
            ->when($status === 'vigentes', fn ($query) => $query->whereNull('anulada_at'))
            ->when($status === 'anulados', fn ($query) => $query->whereNotNull('anulada_at'))
            ->when($date !== null, fn ($query) => $query->whereDate('pagado_at', $date))
            ->orderByDesc('pagado_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $todaySummary = DB::table('pagos_proveedor as pp')
            ->join('medios_pago as mp', 'mp.id', '=', 'pp.medio_pago_id')
            ->whereDate('pp.pagado_at', today()->toDateString())
            ->selectRaw('SUM(CASE WHEN pp.anulada_at IS NULL THEN 1 ELSE 0 END) AS cantidad_pagos')
            ->selectRaw('COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL THEN pp.monto ELSE 0 END), 0) AS total_pagado')
            ->selectRaw('COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL AND mp.es_efectivo = 1 THEN pp.monto ELSE 0 END), 0) AS total_efectivo')
            ->selectRaw('COALESCE(SUM(CASE WHEN pp.anulada_at IS NULL AND mp.es_efectivo = 0 THEN pp.monto ELSE 0 END), 0) AS total_otros_medios')
            ->selectRaw('SUM(CASE WHEN pp.anulada_at IS NOT NULL THEN 1 ELSE 0 END) AS cantidad_anulados')
            ->first();

        return view('pagos-proveedor.index', [
            'authenticatedUser' => $user,
            'date' => $date,
            'payments' => $payments,
            'search' => $search,
            'status' => $status,
            'todaySummary' => $todaySummary,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $loads = CargaProveedor::query()
            ->select(['id', 'numero_carga', 'proveedor_id', 'producto_id', 'fecha_carga', 'costo_total'])
            ->with([
                'proveedor:id,nombre_razon_social',
                'producto:id,nombre',
            ])
            ->vigentes()
            ->whereIn('id', DB::table('vw_saldos_carga_proveedor')
                ->select('carga_id')
                ->where('saldo_pendiente', '>', 0))
            ->orderBy('fecha_carga')
            ->orderBy('id')
            ->get();
        $balances = DB::table('vw_saldos_carga_proveedor')
            ->whereIn('carga_id', $loads->pluck('id'))
            ->get()
            ->keyBy('carga_id');
        $openCashSession = $this->openCashSession($user);
        $preselectedLoadId = $loads->contains('id', $request->integer('carga'))
            ? $request->integer('carga')
            : null;

        return view('pagos-proveedor.create', [
            'authenticatedUser' => $user,
            'balances' => $balances,
            'openCashSession' => $openCashSession,
            'openCashSummary' => $openCashSession === null
                ? null
                : DB::table('vw_resumen_caja_usuario')->where('sesion_caja_id', $openCashSession->getKey())->first(),
            'loads' => $loads,
            'paymentMethods' => MedioPago::query()
                ->select(['id', 'codigo', 'nombre', 'es_efectivo'])
                ->where('activo', true)
                ->orderByDesc('es_efectivo')
                ->orderBy('nombre')
                ->get(),
            'preselectedLoadId' => $preselectedLoadId,
        ]);
    }

    public function store(StorePagoProveedorRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $payment = DB::transaction(function () use ($user, $validated): PagoProveedor {
            $load = CargaProveedor::query()
                ->whereKey($validated['carga_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($load->estaAnulada()) {
                throw ValidationException::withMessages([
                    'carga_id' => 'La carga seleccionada está anulada.',
                ]);
            }
            $paymentMethod = MedioPago::query()
                ->whereKey($validated['medio_pago_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            $activePayments = PagoProveedor::query()
                ->where('carga_id', $load->getKey())
                ->vigentes()
                ->lockForUpdate()
                ->get(['id', 'monto']);
            $pendingBalanceCents = $this->moneyToCents($load->costo_total)
                - $activePayments->sum(fn (PagoProveedor $payment): int => $this->moneyToCents($payment->monto));
            $paymentCents = $this->moneyToCents($validated['monto']);

            if ($pendingBalanceCents <= 0) {
                throw ValidationException::withMessages([
                    'carga_id' => 'La carga seleccionada no tiene saldo pendiente.',
                ]);
            }

            if ($paymentCents > $pendingBalanceCents) {
                throw ValidationException::withMessages([
                    'monto' => 'El pago no puede superar el saldo pendiente de la carga.',
                ]);
            }

            $openCashSession = SesionCaja::query()
                ->where('usuario_id', $user->getKey())
                ->where('fecha_operacion', today()->toDateString())
                ->abiertas()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($paymentMethod->es_efectivo && $openCashSession === null) {
                throw ValidationException::withMessages([
                    'medio_pago_id' => 'Abre una sesión de caja antes de registrar un pago en efectivo.',
                ]);
            }

            if ($paymentMethod->es_efectivo && $openCashSession !== null) {
                $expectedCash = DB::table('vw_resumen_caja_usuario')
                    ->where('sesion_caja_id', $openCashSession->getKey())
                    ->value('efectivo_esperado');

                if ($paymentCents > $this->moneyToCents($expectedCash)) {
                    throw ValidationException::withMessages([
                        'monto' => 'La caja no tiene efectivo suficiente para registrar este pago.',
                    ]);
                }
            }

            $payment = PagoProveedor::query()->create([
                'numero_pago' => 'TMP-'.Str::ulid(),
                'carga_id' => $load->getKey(),
                'sesion_caja_id' => $openCashSession?->getKey(),
                'medio_pago_id' => $paymentMethod->getKey(),
                'monto' => $validated['monto'],
                'pagado_por' => $user->getKey(),
                'pagado_at' => now(),
                'observacion' => $validated['observacion'],
            ]);

            $payment->update([
                'numero_pago' => sprintf('PAG-%s-%06d', now()->format('Ymd'), $payment->getKey()),
            ]);

            return $payment;
        }, 3);

        return to_route('pagos-proveedor.show', $payment)
            ->with('status', "Pago {$payment->numero_pago} registrado correctamente.");
    }

    public function show(Request $request, PagoProveedor $pagoProveedor): View
    {
        $pagoProveedor->load([
            'carga:id,numero_carga,proveedor_id,producto_id,fecha_carga,costo_total',
            'carga.proveedor:id,nombre_razon_social,nro_documento,telefono',
            'carga.producto:id,nombre',
            'sesionCaja:id,usuario_id,fecha_operacion,apertura_at,cierre_at',
            'medioPago:id,codigo,nombre,es_efectivo',
            'pagadoPor:id,nombres,apellidos,usuario',
            'anuladaPor:id,nombres,apellidos,usuario',
        ]);

        return view('pagos-proveedor.show', [
            'authenticatedUser' => $this->authenticatedUser($request),
            'balance' => DB::table('vw_saldos_carga_proveedor')
                ->where('carga_id', $pagoProveedor->carga_id)
                ->firstOrFail(),
            'cashSummary' => $pagoProveedor->sesion_caja_id === null
                ? null
                : DB::table('vw_resumen_caja_usuario')
                    ->where('sesion_caja_id', $pagoProveedor->sesion_caja_id)
                    ->first(),
            'payment' => $pagoProveedor,
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
