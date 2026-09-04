<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSesionCajaRequest;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Services\AutorizacionPinAdministrador;
use App\Services\ResumenJornadaCaja;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SesionCajaController extends Controller
{
    public function __construct(
        private ResumenJornadaCaja $resumenJornada,
        private AutorizacionPinAdministrador $autorizacionPin,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $date = $this->operationalDateFilter($request);

        $sessions = SesionCaja::query()
            ->select(['id', 'usuario_id', 'fecha_operacion', 'apertura_at', 'cierre_at', 'cerrada_por', 'monto_apertura', 'monto_contado_efectivo'])
            ->with('cerradaPor:id,nombres,apellidos,usuario')
            ->where('usuario_id', $user->getKey())
            ->when($date !== null, fn ($query) => $query->whereDate('fecha_operacion', $date))
            ->orderByDesc('apertura_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $summaries = DB::table('vw_resumen_caja_usuario')
            ->whereIn('sesion_caja_id', $sessions->getCollection()->pluck('id'))
            ->get()
            ->keyBy('sesion_caja_id');
        $openSession = SesionCaja::query()
            ->where('usuario_id', $user->getKey())
            ->abiertas()
            ->orderByDesc('id')
            ->first();

        return view('caja.index', [
            'date' => $date,
            'openSession' => $openSession,
            'openSummary' => $openSession === null ? null : $this->summary($openSession),
            'sessions' => $sessions,
            'summaries' => $summaries,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $this->authenticatedUser($request);

        return view('caja.create', [
            'administrators' => $this->autorizacionPin->administradoresDisponibles(),
            'openSession' => SesionCaja::query()
                ->where('usuario_id', $user->getKey())
                ->abiertas()
                ->orderByDesc('id')
                ->first(),
            'pinSetupUser' => $user->esAdministrador() ? $user : null,
            'previousClosedSession' => $this->resumenJornada->ultimaSesionCerradaAnterior($user),
        ]);
    }

    public function store(StoreSesionCajaRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $administrator = $this->autorizacionPin->confirmar($request, 'apertura-caja');

        $cashSession = DB::transaction(function () use ($administrator, $user, $validated): SesionCaja {
            Usuario::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (SesionCaja::query()->where('usuario_id', $user->getKey())->abiertas()->exists()) {
                throw ValidationException::withMessages([
                    'monto_apertura' => 'Ya tienes una sesión de caja abierta.',
                ]);
            }

            return SesionCaja::query()->create([
                'usuario_id' => $user->getKey(),
                'fecha_operacion' => today(),
                'apertura_at' => now(),
                'apertura_autorizada_por' => $administrator->getKey(),
                'monto_apertura' => $validated['monto_apertura'],
            ]);
        }, 3);

        return to_route('caja.show', $cashSession)
            ->with('status', 'Apertura del día registrada correctamente. Ya puedes registrar operaciones.');
    }

    public function show(Request $request, SesionCaja $sesionCaja): View
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeAccess($user, $sesionCaja);
        $sesionCaja->load([
            'usuario:id,nombres,apellidos,usuario',
            'aperturaAutorizadaPor:id,nombres,apellidos,usuario',
            'cerradaPor:id,nombres,apellidos,usuario',
            'cierreAutorizadaPor:id,nombres,apellidos,usuario',
        ]);

        return view('caja.show', [
            'cashSession' => $sesionCaja,
            'summary' => $this->resumenJornada->obtener($sesionCaja),
            'paymentMethodBreakdown' => $this->resumenJornada->desglosePorMedio($sesionCaja),
        ]);
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

    private function summary(SesionCaja $cashSession): ?object
    {
        return DB::table('vw_resumen_caja_usuario')
            ->where('sesion_caja_id', $cashSession->getKey())
            ->first();
    }
}
