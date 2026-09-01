<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularAjusteMercaderiaRequest;
use App\Models\AjusteMercaderia;
use App\Models\Producto;
use App\Models\TipoAjusteMercaderia;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionAjusteMercaderiaController extends Controller
{
    public function create(AjusteMercaderia $ajusteMercaderia): View
    {
        abort_if($ajusteMercaderia->estaAnulado(), 409, 'Este ajuste ya fue anulado.');

        $ajusteMercaderia->load([
            'producto:id,nombre',
            'tipoAjuste:id,codigo,nombre,naturaleza',
            'usuario:id,nombres,apellidos,usuario',
        ]);
        $blockReason = $this->cancellationBlockReason($ajusteMercaderia);
        abort_if($blockReason !== null, 409, $blockReason);

        return view('mercaderia.anulacion', [
            'adjustment' => $ajusteMercaderia,
        ]);
    }

    public function store(AnularAjusteMercaderiaRequest $request, AjusteMercaderia $ajusteMercaderia): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        DB::transaction(function () use ($ajusteMercaderia, $user, $validated): void {
            Producto::query()
                ->whereKey($ajusteMercaderia->producto_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedAdjustment = AjusteMercaderia::query()
                ->whereKey($ajusteMercaderia->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAdjustment->estaAnulado()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Este ajuste ya fue anulado.',
                ]);
            }

            $linkedConciliation = DB::table('conciliacion_ajuste')
                ->where('ajuste_id', $lockedAdjustment->getKey())
                ->lockForUpdate()
                ->get(['conciliacion_id']);

            if ($linkedConciliation->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'No puedes anular un ajuste vinculado a una conciliación de mercadería.',
                ]);
            }

            $type = TipoAjusteMercaderia::query()
                ->whereKey($lockedAdjustment->tipo_ajuste_id)
                ->firstOrFail();

            if ($type->esEntrada()) {
                $stock = DB::table('vw_saldo_mercaderia_actual')
                    ->where('producto_id', $lockedAdjustment->producto_id)
                    ->first();

                if ($stock !== null
                    && ($lockedAdjustment->cantidad_pollos > (int) $stock->pollos_disponibles
                        || $this->kilogramsToGrams($lockedAdjustment->peso_kg) > $this->kilogramsToGrams($stock->kg_disponibles))) {
                    throw ValidationException::withMessages([
                        'motivo_anulacion' => 'No puedes anular esta entrada porque la mercadería ya fue utilizada.',
                    ]);
                }
            }

            $lockedAdjustment->update([
                'anulado_por' => $user->getKey(),
                'anulado_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);
        }, 3);

        return to_route('mercaderia.show', $ajusteMercaderia)
            ->with('status', "Ajuste {$ajusteMercaderia->numero_ajuste} anulado correctamente.");
    }

    private function cancellationBlockReason(AjusteMercaderia $adjustment): ?string
    {
        if (DB::table('conciliacion_ajuste')->where('ajuste_id', $adjustment->getKey())->exists()) {
            return 'Este ajuste está vinculado a una conciliación de mercadería y no puede anularse.';
        }

        if (! $adjustment->tipoAjuste->esEntrada()) {
            return null;
        }

        $stock = DB::table('vw_saldo_mercaderia_actual')
            ->where('producto_id', $adjustment->producto_id)
            ->first();

        if ($stock !== null
            && ($adjustment->cantidad_pollos > (int) $stock->pollos_disponibles
                || $this->kilogramsToGrams($adjustment->peso_kg) > $this->kilogramsToGrams($stock->kg_disponibles))) {
            return 'La mercadería de esta entrada ya fue utilizada y el ajuste no puede anularse.';
        }

        return null;
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
