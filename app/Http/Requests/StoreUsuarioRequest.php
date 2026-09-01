<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario && $user->tienePermiso('USUARIOS_GESTIONAR');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'usuario' => ['required', 'string', 'max:80', 'regex:/^[\pL\pN._-]+$/u', Rule::unique('usuarios', 'usuario')],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'roles' => ['required', 'array', 'min:1', 'max:20'],
            'roles.*' => [
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where(fn (Builder $query): Builder => $query->where('activo', true)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombres.required' => 'Ingresa los nombres del usuario.',
            'nombres.max' => 'Los nombres no pueden superar los 100 caracteres.',
            'apellidos.required' => 'Ingresa los apellidos del usuario.',
            'apellidos.max' => 'Los apellidos no pueden superar los 100 caracteres.',
            'usuario.required' => 'Ingresa un nombre de usuario.',
            'usuario.max' => 'El nombre de usuario no puede superar los 80 caracteres.',
            'usuario.regex' => 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos.',
            'usuario.unique' => 'Ese nombre de usuario ya está registrado.',
            'password.required' => 'Define una contraseña inicial.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'roles.required' => 'Asigna al menos un rol.',
            'roles.min' => 'Asigna al menos un rol.',
            'roles.max' => 'No puedes asignar más de 20 roles.',
            'roles.*.distinct' => 'No puedes asignar el mismo rol más de una vez.',
            'roles.*.exists' => 'Uno de los roles seleccionados no está disponible.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['roles', 'roles.*'])
                    || ! is_array($this->input('roles'))) {
                    return;
                }

                $user = $this->user();

                if ($user instanceof Usuario && ! $user->puedeAsignarRoles($this->input('roles', []))) {
                    $validator->errors()->add(
                        'roles',
                        'No puedes asignar un rol con permisos superiores a los tuyos.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $roles = $this->input('roles', []);

        if (is_array($roles)) {
            $roles = collect($roles)
                ->map(fn (mixed $role): mixed => filter_var($role, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $role)
                ->all();
        }

        $this->merge([
            'nombres' => $this->string('nombres')->squish()->toString(),
            'apellidos' => $this->string('apellidos')->squish()->toString(),
            'usuario' => Str::lower($this->string('usuario')->trim()->toString()),
            'roles' => $roles,
        ]);
    }
}
