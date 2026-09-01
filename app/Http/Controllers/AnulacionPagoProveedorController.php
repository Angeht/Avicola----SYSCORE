<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularPagoProveedorRequest;
use App\Models\CargaProveedor;
use App\Models\PagoProveedor;
use App\Models\SesionCaja;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionPagoProveedorController extends Controller
{
    public function create(PagoProveedor $pagoProveedor): View
    {
        abort_if($pagoProveedor->estaAnulado(), 409, 'Este pago ya fue anulado.');
        abort_if($this->cashSessionIsClosed($pagoProveedor), 409, 'No puedes anular un pago vinculado a una sesión de caja cerrada.');

        $pagoProveedor->load([
            'carga:id,numero_carga,proveedor_id,costo_total',
            'carga.proveedor:id,nombre_razon_social',
            'medioPago:id,nombre,es_efectivo',
            'pagadoPor:id,nombres,apellidos,usuario',
        ]);

        return view('pagos-proveedor.anulacion', [
            'payment' => $pagoProveedor,
        ]);
    }

    public function store(AnularPagoProveedorRequest $request, PagoProveedor $pagoProveedor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        DB::transaction(function () use ($pagoProveedor, $user, $validated): void {
            CargaProveedor::query()
                ->whereKey($pagoProveedor->carga_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPayment = PagoProveedor::query()
                ->whereKey($pagoProveedor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->estaAnulado()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Este pago ya fue anulado.',
                ]);
            }

            if ($lockedPayment->sesion_caja_id !== null) {
                $cashSession = SesionCaja::query()
                    ->whereKey($lockedPayment->sesion_caja_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $cashSession->estaAbierta()) {
                    throw ValidationException::withMessages([
                        'motivo_anulacion' => 'No puedes anular un pago vinculado a una sesión de caja cerrada.',
                    ]);
                }
            }

            $lockedPayment->update([
                'anulada_por' => $user->getKey(),
                'anulada_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);
        }, 3);

        return to_route('pagos-proveedor.show', $pagoProveedor)
            ->with('status', "Pago {$pagoProveedor->numero_pago} anulado correctamente.");
    }

    private function cashSessionIsClosed(PagoProveedor $payment): bool
    {
        return $payment->sesion_caja_id !== null
            && SesionCaja::query()
                ->whereKey($payment->sesion_caja_id)
                ->whereNotNull('cierre_at')
                ->exists();
    }
}
