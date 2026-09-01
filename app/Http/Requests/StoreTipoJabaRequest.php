<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTipoJabaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario && $user->tienePermiso('TIPOS_JABA_GESTIONAR');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80', Rule::unique('tipos_jaba', 'nombre')],
            'tara_referencial_kg' => ['required', 'numeric', 'min:0.001', 'max:9999999.999', 'decimal:0,3'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre del tipo de jaba.',
            'nombre.max' => 'El nombre del tipo de jaba no puede superar los 80 caracteres.',
            'nombre.unique' => 'Ya existe un tipo de jaba con ese nombre.',
            'tara_referencial_kg.required' => 'Ingresa la tara referencial de la jaba.',
            'tara_referencial_kg.numeric' => 'La tara debe ser un número válido.',
            'tara_referencial_kg.min' => 'La tara debe ser mayor que 0.000 kg.',
            'tara_referencial_kg.max' => 'La tara no puede superar 9,999,999.999 kg.',
            'tara_referencial_kg.decimal' => 'La tara puede tener como máximo 3 decimales.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => Str::upper($this->string('nombre')->squish()->toString()),
            'tara_referencial_kg' => $this->string('tara_referencial_kg')->trim()->toString(),
            'descripcion' => $this->filled('descripcion') ? $this->string('descripcion')->squish()->toString() : null,
        ]);
    }
}
