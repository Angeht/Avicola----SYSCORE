<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AutorizacionPinAdministrador
{
    /** @return Collection<int, Usuario> */
    public function administradoresDisponibles(): Collection
    {
        return $this->administradores()
            ->select(['id', 'nombres', 'apellidos', 'usuario'])
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->orderBy('id')
            ->get();
    }

    /**
     * @throws ValidationException
     */
    public function confirmar(Request $request, string $accion): Usuario
    {
        $throttleKey = $this->throttleKey($request, $accion);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'pin_autorizacion' => "Demasiados intentos. Inténtalo nuevamente en {$seconds} segundos.",
            ]);
        }

        $administrator = $this->administradores()
            ->select(['id', 'nombres', 'apellidos', 'usuario', 'pin_autorizacion_hash', 'activo'])
            ->whereKey($request->integer('administrador_id'))
            ->first();

        if (! $administrator instanceof Usuario
            || ! Hash::check($request->string('pin_autorizacion')->toString(), $administrator->pin_autorizacion_hash)) {
            RateLimiter::hit($throttleKey, 600);

            throw ValidationException::withMessages([
                'pin_autorizacion' => 'El administrador o el PIN no son correctos.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $administrator;
    }

    /** @return Builder<Usuario> */
    private function administradores(): Builder
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereNotNull('pin_autorizacion_hash')
            ->whereHas('roles', fn (Builder $query): Builder => $query
                ->where('nombre', 'ADMINISTRADOR')
                ->where('activo', true));
    }

    private function throttleKey(Request $request, string $accion): string
    {
        return implode('|', [
            'confirmar-pin-administrador',
            $accion,
            (string) $request->user()?->getAuthIdentifier(),
            (string) $request->input('administrador_id'),
            (string) $request->ip(),
        ]);
    }
}
