<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConfiguracionEmpresaRequest;
use App\Models\ConfiguracionEmpresa;
use App\Models\TipoDocumento;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class ConfiguracionEmpresaController extends Controller
{
    public function edit(): View
    {
        $company = ConfiguracionEmpresa::query()->firstOrNew(
            ['id' => 1],
            [
                'razon_social' => 'AVÍCOLA - CONFIGURAR',
                'nombre_comercial' => 'AVÍCOLA',
                'mensaje_ticket' => 'Gracias por su compra.',
            ],
        );

        return view('configuracion.edit', [
            'company' => $company,
            'documentTypes' => TipoDocumento::query()
                ->select(['id', 'codigo', 'nombre', 'longitud_maxima'])
                ->where(function (Builder $query) use ($company): void {
                    $query->where('activo', true);

                    if ($company->tipo_documento_id !== null) {
                        $query->orWhereKey($company->tipo_documento_id);
                    }
                })
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function update(UpdateConfiguracionEmpresaRequest $request): RedirectResponse
    {
        ConfiguracionEmpresa::query()->updateOrCreate(
            ['id' => 1],
            $request->validated(),
        );

        return to_route('configuracion.edit')
            ->with('status', 'Configuración de la empresa actualizada correctamente.');
    }
}
