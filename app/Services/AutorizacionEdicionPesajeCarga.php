<?php

namespace App\Services;

use App\Models\PesajeCarga;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AutorizacionEdicionPesajeCarga
{
    public const MINUTOS_VIGENCIA = 10;

    private const SESSION_KEY_PREFIX = 'pesajes-carga-edicion:';

    public function conceder(Request $request, PesajeCarga $weighing, Usuario $administrator): void
    {
        $request->session()->cache()->put(
            $this->sessionKey($weighing),
            [
                'operador_id' => $request->user()?->getAuthIdentifier(),
                'administrador_id' => $administrator->getKey(),
            ],
            now()->addMinutes(self::MINUTOS_VIGENCIA),
        );
    }

    public function administrador(Request $request, PesajeCarga $weighing): ?Usuario
    {
        $authorization = $request->session()->cache()->get($this->sessionKey($weighing));

        if (! is_array($authorization)
            || (int) ($authorization['operador_id'] ?? 0) !== (int) $request->user()?->getAuthIdentifier()) {
            $this->revocar($request, $weighing);

            return null;
        }

        return Usuario::query()
            ->select(['id', 'nombres', 'apellidos', 'usuario', 'activo'])
            ->whereKey((int) ($authorization['administrador_id'] ?? 0))
            ->where('activo', true)
            ->whereNotNull('pin_autorizacion_hash')
            ->whereHas('roles', fn (Builder $query): Builder => $query
                ->where('nombre', 'ADMINISTRADOR')
                ->where('activo', true))
            ->first();
    }

    public function revocar(Request $request, PesajeCarga $weighing): void
    {
        $request->session()->cache()->forget($this->sessionKey($weighing));
    }

    private function sessionKey(PesajeCarga $weighing): string
    {
        return self::SESSION_KEY_PREFIX.$weighing->getKey();
    }
}
