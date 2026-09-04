<?php

namespace App\Http\Controllers;

use App\Http\Requests\AutorizarEdicionPesajeCargaRequest;
use App\Models\CargaProveedor;
use App\Models\PesajeCarga;
use App\Models\Usuario;
use App\Services\AutorizacionEdicionPesajeCarga;
use App\Services\AutorizacionPinAdministrador;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AutorizacionEdicionPesajeCargaController extends Controller
{
    public function __construct(private AutorizacionPinAdministrador $autorizacionPin) {}

    public function create(Request $request, CargaProveedor $cargaProveedor, PesajeCarga $pesaje): View
    {
        $this->ensureLoadCanBeEdited($cargaProveedor);
        $actor = $request->user();

        $cargaProveedor->load([
            'proveedor:id,nombre_razon_social',
            'producto:id,nombre',
        ]);
        $pesaje->load('tipoJaba:id,nombre');

        return view('cargas-proveedor.pesajes.autorizar-edicion', [
            'administrators' => $this->autorizacionPin->administradoresDisponibles(),
            'hasActivePayments' => $cargaProveedor->tienePagosVigentes(),
            'load' => $cargaProveedor,
            'pinSetupUser' => $actor instanceof Usuario && $actor->esAdministrador() ? $actor : null,
            'position' => $cargaProveedor->pesajes()
                ->where('id', '<', $pesaje->getKey())
                ->count() + 1,
            'weighing' => $pesaje,
            'validityMinutes' => AutorizacionEdicionPesajeCarga::MINUTOS_VIGENCIA,
        ]);
    }

    public function store(
        AutorizarEdicionPesajeCargaRequest $request,
        CargaProveedor $cargaProveedor,
        PesajeCarga $pesaje,
        AutorizacionEdicionPesajeCarga $authorization,
    ): RedirectResponse {
        $this->ensureLoadCanBeEdited($cargaProveedor);

        $authorization->conceder($request, $pesaje, $request->administradorAutorizador());

        return to_route('cargas-proveedor.pesajes.edit', [$cargaProveedor, $pesaje]);
    }

    private function ensureLoadCanBeEdited(CargaProveedor $load): void
    {
        abort_if($load->estaAnulada(), 409, 'No se puede editar un pesaje de una carga anulada.');
    }
}
