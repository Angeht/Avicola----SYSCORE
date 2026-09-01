<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditFiltersRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accion' => ['nullable', 'string', Rule::in(['INSERT', 'UPDATE', 'DELETE', 'ANULAR', 'LOGIN', 'OTRO'])],
            'buscar' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tabla' => ['nullable', 'string', 'max:80'],
            'usuario_id' => ['nullable', 'integer', 'exists:usuarios,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'accion' => $this->filled('accion') ? $this->string('accion')->trim()->upper()->toString() : null,
            'buscar' => $this->filled('buscar') ? $this->string('buscar')->trim()->toString() : null,
            'tabla' => $this->filled('tabla') ? $this->string('tabla')->trim()->toString() : null,
        ]);
    }
}
