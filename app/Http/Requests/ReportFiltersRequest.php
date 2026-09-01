<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFiltersRequest extends FormRequest
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
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'desde' => ['required', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'string', Rule::in(['TODOS', 'ACTIVA', 'ANULADA', 'PENDIENTE', 'PARCIAL', 'SALDADA', 'SALDO_FAVOR', 'ABIERTA', 'CERRADA'])],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'usuario_id' => ['nullable', 'integer', 'exists:usuarios,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $report = (string) $this->route('report');
        $isOutstandingBalance = in_array($report, ['cuentas-cobrar', 'deudas-proveedores'], true);

        $this->merge([
            'desde' => $this->filled('desde')
                ? $this->input('desde')
                : ($isOutstandingBalance ? '2000-01-01' : now()->startOfMonth()->toDateString()),
            'estado' => $this->filled('estado')
                ? $this->string('estado')->trim()->upper()->toString()
                : ($report === 'ventas' ? 'ACTIVA' : 'TODOS'),
            'hasta' => $this->filled('hasta') ? $this->input('hasta') : today()->toDateString(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.required' => 'Selecciona la fecha inicial.',
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.required' => 'Selecciona la fecha final.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ];
    }
}
