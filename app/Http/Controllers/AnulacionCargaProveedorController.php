<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularCargaProveedorRequest;
use App\Models\CargaProveedor;
use App\Models\PagoProveedor;
use App\Models\ProcesoBeneficiado;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnulacionCargaProveedorController extends Controller
{
    public function create(CargaProveedor $cargaProveedor): View
    {
        abort_if($cargaProveedor->estaAnulada(), 409, 'Esta carga ya fue anulada.');
        abort_if($this->hasActivePayments($cargaProveedor), 409, 'Anula primero los pagos vigentes de la carga.');
        abort_if($this->hasActiveBeneficiaryProcesses($cargaProveedor), 409, 'Anula primero los procesos de beneficiado vigentes de la carga.');

        $cargaProveedor->load([
            'proveedor:id,nombre_razon_social,nro_documento',
            'producto:id,nombre',
            'recibidoPor:id,nombres,apellidos,usuario',
        ]);

        return view('cargas-proveedor.anulacion', [
            'balance' => DB::table('vw_saldos_carga_proveedor')
                ->where('carga_id', $cargaProveedor->getKey())
                ->firstOrFail(),
            'load' => $cargaProveedor,
            'summary' => DB::table('vw_resumen_carga')
                ->where('carga_id', $cargaProveedor->getKey())
                ->firstOrFail(),
        ]);
    }

    public function store(AnularCargaProveedorRequest $request, CargaProveedor $cargaProveedor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);
        $validated = $request->validated();

        DB::transaction(function () use ($cargaProveedor, $user, $validated): void {
            $lockedLoad = CargaProveedor::query()
                ->whereKey($cargaProveedor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLoad->estaAnulada()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Esta carga ya fue anulada.',
                ]);
            }

            Producto::query()
                ->whereKey($lockedLoad->producto_id)
                ->lockForUpdate()
                ->firstOrFail();
            $activePayments = PagoProveedor::query()
                ->where('carga_id', $lockedLoad->getKey())
                ->vigentes()
                ->lockForUpdate()
                ->get(['id']);
            $activeBeneficiaryProcesses = ProcesoBeneficiado::query()
                ->where('carga_proveedor_id', $lockedLoad->getKey())
                ->vigentes()
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($activePayments->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Anula primero los pagos vigentes de la carga.',
                ]);
            }

            if ($activeBeneficiaryProcesses->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Anula primero los procesos de beneficiado vigentes de la carga.',
                ]);
            }

            $lockedLoad->update([
                'anulada_por' => $user->getKey(),
                'anulada_at' => now(),
                'motivo_anulacion' => $validated['motivo_anulacion'],
            ]);
        }, 3);

        return to_route('cargas-proveedor.show', $cargaProveedor)
            ->with('status', "Carga {$cargaProveedor->numero_carga} anulada correctamente.");
    }

    private function hasActivePayments(CargaProveedor $load): bool
    {
        return $load->pagosProveedor()
            ->whereNull('anulada_at')
            ->exists();
    }

    private function hasActiveBeneficiaryProcesses(CargaProveedor $load): bool
    {
        return $load->procesosBeneficiado()
            ->whereNull('anulado_at')
            ->exists();
    }
}
