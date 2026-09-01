<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AutorizarEdicionPesajeCargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tienePermiso('CARGAS_REGISTRAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'administrador_id' => ['required', 'integer'],
            'pin_autorizacion' => ['required', 'digits:4'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'administrador_id.required' => 'Selecciona el administrador que autoriza la edición.',
            'administrador_id.integer' => 'El administrador seleccionado no es válido.',
            'pin_autorizacion.required' => 'Ingresa el PIN administrativo.',
            'pin_autorizacion.digits' => 'El PIN administrativo debe tener exactamente 4 dígitos.',
        ];
    }

    public function administradorAutorizador(): Usuario
    {
        $this->ensureIsNotRateLimited();

        $administrator = Usuario::query()
            ->select(['id', 'nombres', 'apellidos', 'usuario', 'pin_autorizacion_hash', 'activo'])
            ->whereKey($this->integer('administrador_id'))
            ->where('activo', true)
            ->whereNotNull('pin_autorizacion_hash')
            ->whereHas('roles', fn (Builder $query): Builder => $query
                ->where('nombre', 'ADMINISTRADOR')
                ->where('activo', true))
            ->first();

        if (! $administrator instanceof Usuario
            || ! Hash::check($this->string('pin_autorizacion')->toString(), $administrator->pin_autorizacion_hash)) {
            RateLimiter::hit($this->throttleKey(), 600);

            throw ValidationException::withMessages([
                'pin_autorizacion' => 'El administrador o el PIN no son correctos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $administrator;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'pin_autorizacion' => "Demasiados intentos. Inténtalo nuevamente en {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return implode('|', [
            'autorizar-edicion-pesaje',
            (string) $this->user()?->getAuthIdentifier(),
            (string) $this->input('administrador_id'),
            (string) $this->ip(),
        ]);
    }
}
