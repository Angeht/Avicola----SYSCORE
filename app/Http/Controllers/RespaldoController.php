<?php

namespace App\Http\Controllers;

use App\Contracts\GestorRespaldos;
use App\Models\ConfiguracionRespaldo;
use App\Models\Respaldo;
use App\Models\RestauracionRespaldo;
use App\Models\Usuario;
use App\Services\TareaProgramadaWindows;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RespaldoController extends Controller
{
    public function index(Request $request, GestorRespaldos $manager, TareaProgramadaWindows $scheduledTask): View
    {
        $user = $this->authenticatedUser($request);
        $backups = Respaldo::query()
            ->with([
                'creadoPor:id,nombres,apellidos,usuario',
                'verificadoPor:id,nombres,apellidos,usuario',
                'eliminadoPor:id,nombres,apellidos,usuario',
            ])
            ->orderByDesc('creado_at')
            ->orderByDesc('id')
            ->paginate(12);

        return view('respaldos.index', [
            'backups' => $backups,
            'configuration' => ConfiguracionRespaldo::singleton()->load('actualizadoPor:id,nombres,apellidos,usuario'),
            'engineAvailable' => $manager->motorDisponible(),
            'lastBackup' => Respaldo::query()
                ->where('estado', Respaldo::ESTADO_COMPLETADO)
                ->whereNull('eliminado_at')
                ->latest('creado_at')
                ->first(),
            'restorations' => RestauracionRespaldo::query()
                ->with('solicitadoPor:id,nombres,apellidos,usuario')
                ->orderByDesc('iniciado_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'schedulerCompatible' => $scheduledTask->esCompatible(),
            'schedulerInstalled' => $scheduledTask->estaInstalada(),
            'storageBytes' => (int) Respaldo::query()
                ->where('estado', Respaldo::ESTADO_COMPLETADO)
                ->whereNull('eliminado_at')
                ->sum('tamano_bytes'),
            'user' => $user,
        ]);
    }

    public function store(Request $request, GestorRespaldos $manager): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        try {
            $backup = $manager->crear(Respaldo::TIPO_MANUAL, $user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'respaldo' => 'No se pudo crear la copia. Revisa que MySQL esté activo y vuelve a intentarlo.',
            ]);
        }

        return to_route('respaldos.index')
            ->with('status', "Copia {$backup->nombre_archivo} creada correctamente.");
    }

    public function download(Respaldo $respaldo): StreamedResponse
    {
        abort_unless($respaldo->estaDisponible(), 404);
        abort_unless($respaldo->disco === config('respaldos.disk', 'local'), 404);
        $disk = Storage::disk($respaldo->disco);
        abort_unless($disk->exists($respaldo->ruta), 404);

        return $disk->download($respaldo->ruta, basename($respaldo->nombre_archivo), [
            'Content-Type' => 'application/sql',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Respaldo $respaldo, GestorRespaldos $manager): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        try {
            $manager->eliminar($respaldo, $user);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'respaldo' => 'No fue posible eliminar la copia seleccionada.',
            ]);
        }

        return to_route('respaldos.index')
            ->with('status', "La copia {$respaldo->nombre_archivo} fue eliminada permanentemente del almacenamiento.");
    }

    private function authenticatedUser(Request $request): Usuario
    {
        $user = $request->user();
        abort_unless($user instanceof Usuario, 403);

        return $user;
    }
}
