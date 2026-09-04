<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularCobranzaRequest;
use App\Models\AjusteCliente;
use App\Models\Cobranza;
use App\Models\SesionCaja;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionCobranzaController extends Controller
{
    public function create(Cobranza $cobranza): View
    {
        abort_if($cobranza->estaAnulada(), 409, 'Esta cobranza ya fue anulada.');
        abort_if($this->cashSessionIsClosed($cobranza), 409, 'No puedes anular una cobranza vinculada a una sesión de caja cerrada.');

        $cobranza->load([
            'cliente:id,nombres_razon_social,nro_documento',
            'medioPago:id,nombre,es_efectivo',
            'usuario:id,nombres,apellidos,usuario',
            'aplicaciones' => fn ($query) => $query
                ->select(['cobranza_id', 'venta_id', 'monto_aplicado'])
                ->with('venta:id,numero_venta')
                ->orderBy('venta_id'),
        ]);

        return view('cobranzas.anulacion', [
            'collection' => $cobranza,
        ]);
    }

    public function store(AnularCobranzaRequest $request, Cobranza $cobranza): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        DB::transaction(function () use ($cobranza, $user, $validated): void {
            $saleIds = DB::table('aplicacion_cobranzas')
                ->where('cobranza_id', $cobranza->getKey())
                ->orderBy('venta_id')
                ->pluck('venta_id');

            Venta::query()
                ->whereKey($saleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $lockedCollection = Cobranza::query()
                ->whereKey($cobranza->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $roundingAdjustments = AjusteCliente::query()
                ->where('cobranza_id', $lockedCollection->getKey())
                ->vigentes()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedCollection->estaAnulada()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Esta cobranza ya fue anulada.',
                ]);
            }

            if ($lockedCollection->sesion_caja_id !== null) {
                $cashSession = SesionCaja::query()
                    ->whereKey($lockedCollection->sesion_caja_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $cashSession->estaAbierta()) {
                    throw ValidationException::withMessages([
                        'motivo_anulacion' => 'No puedes anular una cobranza vinculada a una sesión de caja cerrada.',
                    ]);
                }
            }

            $lockedCollection->update([
                'anulada_por' => $user->getKey(),
                'anulada_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);

            foreach ($roundingAdjustments as $adjustment) {
                $adjustment->update([
                    'anulado_por' => $user->getKey(),
                    'anulado_at' => now(),
                    'motivo_anulacion' => "Anulación de la cobranza {$lockedCollection->numero_cobranza}: {$validated['motivo_anulacion']}",
                ]);
            }
        }, 3);

        return to_route('cobranzas.show', $cobranza)
            ->with('status', "Cobranza {$cobranza->numero_cobranza} anulada correctamente.");
    }

    private function cashSessionIsClosed(Cobranza $collection): bool
    {
        return $collection->sesion_caja_id !== null
            && SesionCaja::query()
                ->whereKey($collection->sesion_caja_id)
                ->whereNotNull('cierre_at')
                ->exists();
    }
}
