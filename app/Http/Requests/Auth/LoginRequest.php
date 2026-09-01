<?php

namespace App\Http\Requests\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.required' => 'Ingresa tu usuario.',
            'usuario.max' => 'El usuario no puede superar los 80 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
        ];
    }

    public function authenticate(): Usuario
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt([
            'usuario' => $this->string('usuario')->trim()->toString(),
            'password' => $this->string('password')->toString(),
            'activo' => true,
        ]);

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'usuario' => 'El usuario o la contraseña no son correctos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = Auth::user();

        if (! $user instanceof Usuario) {
            Auth::logout();

            throw ValidationException::withMessages([
                'usuario' => 'No fue posible iniciar la sesión.',
            ]);
        }

        $user->forceFill(['ultimo_acceso' => now()])->save();

        return $user;
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('usuario')->trim()->toString()).'|'.$this->ip(),
        );
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'usuario' => "Demasiados intentos. Inténtalo nuevamente en {$seconds} segundos.",
        ]);
    }
}
