<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagoProveedorRequest;
use App\Models\AjusteProveedor;
use App\Models\CargaProveedor;
use App\Models\MedioPago;
use App\Models\PagoProveedor;
use App\Models\Proveedor;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $selectedProviderId = $request->integer('proveedor') ?: null;

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
            ->when($selectedProviderId, fn ($query, int $providerId) => $query->whereHas(
                'carga',
                fn ($query) => $query->where('proveedor_id', $providerId),
            ))
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
            'providers' => Proveedor::query()
                ->select(['id', 'nombre_razon_social'])
                ->orderBy('nombre_razon_social')
                ->get(),
            'search' => $search,
            'selectedProviderId' => $selectedProviderId,
            'status' => $status,
            'supplierDebts' => $this->pendingSupplierDebts(),
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
        $supplierDebts = $this->pendingSupplierDebts();
        $requestedProviderId = $request->integer('proveedor') ?: CargaProveedor::query()
            ->whereKey($request->integer('carga'))
            ->value('proveedor_id');
        $preselectedProviderId = $supplierDebts->contains('proveedor_id', $requestedProviderId)
            ? $requestedProviderId
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
            'preselectedProviderId' => $preselectedProviderId,
            'supplierDebts' => $supplierDebts,
            'canAdjustProvider' => $user->tienePermiso('PROVEEDORES_AJUSTAR'),
        ]);
    }

    public function store(StorePagoProveedorRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $result = DB::transaction(function () use ($user, $validated): array {
            $provider = Proveedor::query()
                ->whereKey($validated['proveedor_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $paymentMethod = MedioPago::query()
                ->whereKey($validated['medio_pago_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->firstOrFail();
            $loads = CargaProveedor::query()
                ->select(['id', 'costo_total'])
                ->whereBelongsTo($provider)
                ->vigentes()
                ->where('costo_total', '>', 0)
                ->orderBy('fecha_carga')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $loadIds = $loads->pluck('id');
            $activePayments = PagoProveedor::query()
                ->whereIn('carga_id', $loadIds)
                ->vigentes()
                ->lockForUpdate()
                ->get(['id', 'carga_id', 'monto'])
                ->groupBy('carga_id');
            $activeAdjustments = AjusteProveedor::query()
                ->whereIn('carga_id', $loadIds)
                ->vigentes()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'carga_id', 'monto'])
                ->groupBy('carga_id');
            $pendingLoads = $loads
                ->map(function (CargaProveedor $load) use ($activePayments, $activeAdjustments): array {
                    $paidCents = $activePayments->get($load->getKey(), collect())
                        ->sum(fn (PagoProveedor $payment): int => $this->moneyToCents($payment->monto));
                    $adjustedCents = $activeAdjustments->get($load->getKey(), collect())
                        ->sum(fn (AjusteProveedor $adjustment): int => $this->moneyToCents($adjustment->monto));

                    return [
                        'load' => $load,
                        'balance_cents' => max($this->moneyToCents($load->costo_total) - $paidCents - $adjustedCents, 0),
                    ];
                })
                ->filter(fn (array $pendingLoad): bool => $pendingLoad['balance_cents'] > 0)
                ->values();
            $pendingBalanceCents = $pendingLoads->sum('balance_cents');
            $paymentCents = $this->moneyToCents($validated['monto']);
            $discountCents = $validated['aplicar_descuento']
                ? $this->moneyToCents($validated['monto_descuento'])
                : 0;

            if ($pendingBalanceCents <= 0) {
                throw ValidationException::withMessages([
                    'proveedor_id' => 'El proveedor seleccionado no tiene deuda pendiente.',
                ]);
            }

            if ($paymentCents + $discountCents > $pendingBalanceCents) {
                throw ValidationException::withMessages([
                    $discountCents > 0 ? 'monto_descuento' : 'monto' => $discountCents > 0
                        ? 'El pago y el descuento no pueden superar la deuda total del proveedor.'
                        : 'El pago no puede superar la deuda total del proveedor.',
                ]);
            }

            if ($discountCents > 0 && ! $user->tienePermiso('PROVEEDORES_AJUSTAR')) {
                throw ValidationException::withMessages([
                    'aplicar_descuento' => 'No tienes permiso para reconocer descuentos del proveedor.',
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

            $remainingPaymentCents = $paymentCents;
            $remainingDiscountCents = $discountCents;
            $createdPayments = collect();
            $affectedLoadIds = collect();
            $paidAt = now();

            foreach ($pendingLoads as $pendingLoad) {
                /** @var CargaProveedor $load */
                $load = $pendingLoad['load'];
                $availableCents = $pendingLoad['balance_cents'];
                $paymentForLoadCents = min($remainingPaymentCents, $availableCents);
                $payment = null;

                if ($paymentForLoadCents > 0) {
                    $payment = PagoProveedor::query()->create([
                        'numero_pago' => 'TMP-'.Str::ulid(),
                        'carga_id' => $load->getKey(),
                        'sesion_caja_id' => $openCashSession?->getKey(),
                        'medio_pago_id' => $paymentMethod->getKey(),
                        'monto' => $paymentForLoadCents / 100,
                        'pagado_por' => $user->getKey(),
                        'pagado_at' => $paidAt,
                        'observacion' => $validated['observacion'],
                    ]);
                    $payment->update([
                        'numero_pago' => sprintf('PAG-%s-%06d', $paidAt->format('Ymd'), $payment->getKey()),
                    ]);
                    $createdPayments->push($payment);
                    $affectedLoadIds->push($load->getKey());
                    $remainingPaymentCents -= $paymentForLoadCents;
                    $availableCents -= $paymentForLoadCents;
                }

                $discountForLoadCents = min($remainingDiscountCents, $availableCents);

                if ($discountForLoadCents > 0) {
                    $adjustment = AjusteProveedor::query()->create([
                        'numero_ajuste' => 'TMP-'.Str::ulid(),
                        'carga_id' => $load->getKey(),
                        'pago_proveedor_id' => $payment?->getKey(),
                        'tipo' => 'DESCUENTO',
                        'monto' => $discountForLoadCents / 100,
                        'motivo' => $validated['motivo_descuento'],
                        'usuario_id' => $user->getKey(),
                        'fecha_ajuste' => $paidAt,
                    ]);
                    $adjustment->update([
                        'numero_ajuste' => sprintf('AJP-%s-%06d', $paidAt->format('Ymd'), $adjustment->getKey()),
                    ]);
                    $affectedLoadIds->push($load->getKey());
                    $remainingDiscountCents -= $discountForLoadCents;
                }

                if ($remainingPaymentCents === 0 && $remainingDiscountCents === 0) {
                    break;
                }
            }

            if ($remainingPaymentCents > 0 || $remainingDiscountCents > 0) {
                throw ValidationException::withMessages([
                    'monto' => 'La deuda del proveedor cambió mientras registrabas el pago. Revisa el saldo e inténtalo nuevamente.',
                ]);
            }

            return [
                'first_payment' => $createdPayments->firstOrFail(),
                'payment_count' => $createdPayments->count(),
                'load_count' => $affectedLoadIds->unique()->count(),
            ];
        }, 3);

        /** @var PagoProveedor $firstPayment */
        $firstPayment = $result['first_payment'];
        $status = $result['load_count'] === 1
            ? "Pago {$firstPayment->numero_pago} registrado correctamente."
            : "Pago distribuido automáticamente entre {$result['load_count']} cargas pendientes del proveedor.";

        return to_route('pagos-proveedor.show', $firstPayment)
            ->with('status', $status);
    }

    public function show(Request $request, PagoProveedor $pagoProveedor): View
    {
        $pagoProveedor->load([
            'carga:id,numero_carga,proveedor_id,producto_id,fecha_carga,costo_total',
            'carga.proveedor:id,nombre_razon_social,nro_documento,telefono,numero_cuenta',
            'carga.producto:id,nombre',
            'sesionCaja:id,usuario_id,fecha_operacion,apertura_at,cierre_at',
            'medioPago:id,codigo,nombre,es_efectivo',
            'pagadoPor:id,nombres,apellidos,usuario',
            'anuladaPor:id,nombres,apellidos,usuario',
            'ajustesProveedor:id,numero_ajuste,carga_id,pago_proveedor_id,tipo,monto,motivo,anulado_at',
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
            'providerDebt' => (float) DB::table('vw_saldos_carga_proveedor')
                ->where('proveedor_id', $pagoProveedor->carga->proveedor_id)
                ->where('saldo_pendiente', '>', 0)
                ->sum('saldo_pendiente'),
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

    private function pendingSupplierDebts(): Collection
    {
        return DB::table('vw_saldos_carga_proveedor as s')
            ->join('proveedores as p', 'p.id', '=', 's.proveedor_id')
            ->where('s.saldo_pendiente', '>', 0)
            ->selectRaw('p.id as proveedor_id, p.nombre_razon_social as proveedor, p.numero_cuenta')
            ->selectRaw('COUNT(*) as cargas_pendientes')
            ->selectRaw('ROUND(SUM(s.costo_total), 2) as costo_total')
            ->selectRaw('ROUND(SUM(s.total_pagado), 2) as total_pagado')
            ->selectRaw('ROUND(SUM(s.total_ajustado), 2) as total_ajustado')
            ->selectRaw('ROUND(SUM(s.saldo_pendiente), 2) as deuda_total')
            ->groupBy('p.id', 'p.nombre_razon_social', 'p.numero_cuenta')
            ->orderByDesc('deuda_total')
            ->orderBy('p.nombre_razon_social')
            ->get();
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
