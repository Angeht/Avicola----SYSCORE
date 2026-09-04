<?php

namespace App\Http\Requests;

use App\Models\Permiso;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRolRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:60', 'regex:/^[\pL\pN _-]+$/u', Rule::unique('roles', 'nombre')],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos' => ['required', 'array', 'min:1', 'max:100'],
            'permisos.*' => ['integer', 'distinct', Rule::exists('permisos', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre del rol.',
            'nombre.max' => 'El nombre del rol no puede superar los 60 caracteres.',
            'nombre.regex' => 'El nombre del rol solo puede contener letras, números, espacios y guiones.',
            'nombre.unique' => 'Ya existe un rol con ese nombre.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
            'permisos.required' => 'Selecciona al menos un permiso.',
            'permisos.min' => 'Selecciona al menos un permiso.',
            'permisos.*.distinct' => 'No puedes seleccionar el mismo permiso más de una vez.',
            'permisos.*.exists' => 'Uno de los permisos seleccionados no existe.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['permisos', 'permisos.*'])
                    || ! is_array($this->input('permisos'))) {
                    return;
                }

                $permissionIds = $this->input('permisos', []);

                if (Permiso::incluyePermisoExclusivoAdministradorSistema($permissionIds)) {
                    $validator->errors()->add(
                        'permisos',
                        'Los permisos de configuración y respaldos son exclusivos del rol ADMINISTRADOR.',
                    );

                    return;
                }

                $user = $this->user();

                if ($user instanceof Usuario && ! $user->puedeConcederPermisos($permissionIds)) {
                    $validator->errors()->add(
                        'permisos',
                        'No puedes conceder permisos que no tienes asignados.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $permissions = $this->input('permisos', []);

        if (is_array($permissions)) {
            $permissions = collect($permissions)
                ->map(fn (mixed $permission): mixed => filter_var($permission, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $permission)
                ->all();
        }

        $this->merge([
            'nombre' => Str::upper($this->string('nombre')->squish()->toString()),
            'descripcion' => $this->filled('descripcion') ? $this->string('descripcion')->squish()->toString() : null,
            'permisos' => $permissions,
        ]);
    }
}
