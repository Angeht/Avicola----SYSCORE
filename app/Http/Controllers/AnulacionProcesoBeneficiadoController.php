<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularProcesoBeneficiadoRequest;
use App\Models\CargaProveedor;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionProcesoBeneficiadoController extends Controller
{
    public function create(ProcesoBeneficiado $procesoBeneficiado): View
    {
        abort_if($procesoBeneficiado->estaAnulado(), 409, 'Este proceso de beneficiado ya fue anulado.');

        $procesoBeneficiado->load([
            'cargaProveedor:id,numero_carga,producto_id,proveedor_id',
            'cargaProveedor.producto:id,nombre',
            'cargaProveedor.proveedor:id,nombre_razon_social',
            'productoDestino:id,nombre',
        ]);
        $blockReason = $this->cancellationBlockReason($procesoBeneficiado);
        abort_if($blockReason !== null, 409, $blockReason);

        return view('beneficiados.anulacion', [
            'process' => $procesoBeneficiado,
        ]);
    }

    public function store(AnularProcesoBeneficiadoRequest $request, ProcesoBeneficiado $procesoBeneficiado): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        DB::transaction(function () use ($procesoBeneficiado, $user, $validated): void {
            $load = CargaProveedor::query()
                ->whereKey($procesoBeneficiado->carga_proveedor_id)
                ->lockForUpdate()
                ->firstOrFail();
            Producto::query()
                ->whereKey([$load->producto_id, $procesoBeneficiado->producto_destino_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $lockedProcess = ProcesoBeneficiado::query()
                ->whereKey($procesoBeneficiado->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProcess->estaAnulado()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Este proceso de beneficiado ya fue anulado.',
                ]);
            }

            $destinationStock = DB::table('vw_saldo_mercaderia_actual')
                ->where('producto_id', $lockedProcess->producto_destino_id)
                ->first();

            if ($destinationStock !== null
                && $this->kilogramsToGrams($lockedProcess->peso_resultante_kg) > $this->kilogramsToGrams($destinationStock->kg_disponibles)) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'No puedes anular el proceso porque parte del producto beneficiado ya fue utilizado.',
                ]);
            }

            $lockedProcess->update([
                'anulado_por' => $user->getKey(),
                'anulado_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);
        }, 3);

        return to_route('beneficiados.show', $procesoBeneficiado)
            ->with('status', "Beneficiado {$procesoBeneficiado->numero_proceso} anulado correctamente.");
    }

    private function cancellationBlockReason(ProcesoBeneficiado $process): ?string
    {
        $destinationStock = DB::table('vw_saldo_mercaderia_actual')
            ->where('producto_id', $process->producto_destino_id)
            ->first();

        if ($destinationStock !== null
            && $this->kilogramsToGrams($process->peso_resultante_kg) > $this->kilogramsToGrams($destinationStock->kg_disponibles)) {
            return 'Parte del producto beneficiado ya fue utilizada y el proceso no puede anularse.';
        }

        return null;
    }

    private function kilogramsToGrams(mixed $kilograms): int
    {
        return (int) round((float) $kilograms * 1000);
    }
}
