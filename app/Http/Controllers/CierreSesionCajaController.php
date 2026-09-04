<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseSesionCajaRequest;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Services\AutorizacionPinAdministrador;
use App\Services\ResumenJornadaCaja;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CierreSesionCajaController extends Controller
{
    public function __construct(
        private ResumenJornadaCaja $resumenJornada,
        private AutorizacionPinAdministrador $autorizacionPin,
    ) {}

    public function create(Request $request, SesionCaja $sesionCaja): View
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeAccess($user, $sesionCaja);
        abort_unless($sesionCaja->estaAbierta(), 409, 'Esta sesión de caja ya fue cerrada.');

        return view('caja.cierre', [
            'administrators' => $this->autorizacionPin->administradoresDisponibles(),
            'cashSession' => $sesionCaja,
            'pinSetupUser' => $user->esAdministrador() ? $user : null,
            'summary' => $this->resumenJornada->obtener($sesionCaja),
            'paymentMethodBreakdown' => $this->resumenJornada->desglosePorMedio($sesionCaja),
        ]);
    }

    public function store(CloseSesionCajaRequest $request, SesionCaja $sesionCaja): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $administrator = $this->autorizacionPin->confirmar($request, 'cierre-caja');

        DB::transaction(function () use ($administrator, $sesionCaja, $user, $validated): void {
            $lockedSession = SesionCaja::query()
                ->whereKey($sesionCaja->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorizeAccess($user, $lockedSession);

            if (! $lockedSession->estaAbierta()) {
                throw ValidationException::withMessages([
                    'monto_contado_efectivo' => 'Esta sesión de caja ya fue cerrada.',
                ]);
            }

            $expectedCash = (float) DB::table('vw_resumen_caja_usuario')
                ->where('sesion_caja_id', $lockedSession->getKey())
                ->value('efectivo_esperado');
            $countedCash = (float) $validated['monto_contado_efectivo'];
            $hasDifference = (int) round(($countedCash - $expectedCash) * 100) !== 0;

            if ($hasDifference && blank($validated['observacion_cierre'])) {
                throw ValidationException::withMessages([
                    'observacion_cierre' => 'Explica la diferencia encontrada en el efectivo.',
                ]);
            }

            $lockedSession->update([
                'cierre_at' => now(),
                'cerrada_por' => $user->getKey(),
                'cierre_autorizada_por' => $administrator->getKey(),
                'monto_contado_efectivo' => $validated['monto_contado_efectivo'],
                'observacion_cierre' => $validated['observacion_cierre'],
            ]);
        }, 3);

        return to_route('caja.show', $sesionCaja)
            ->with('status', 'Cierre del día registrado correctamente. El arqueo quedó guardado.');
    }

    private function authenticatedUser(Request $request): Usuario
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        return $user;
    }

    private function authorizeAccess(Usuario $user, SesionCaja $cashSession): void
    {
        abort_unless($cashSession->usuario_id === $user->getKey() || $user->esAdministrador(), 403);
    }
}
