<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario
            && $user->tieneAlgunPermiso(['VENTAS_REGISTRAR', 'COBRANZAS_REGISTRAR']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('cliente');
        $uniqueDocument = Rule::unique('clientes', 'nro_documento')->where(
            fn (Builder $query): Builder => $query->where('tipo_documento_id', $this->integer('tipo_documento_id')),
        );

        if ($client instanceof Cliente) {
            $uniqueDocument->ignore($client);
        }

        return [
            'tipo_documento_id' => [
                'nullable',
                'required_with:nro_documento',
                Rule::exists('tipos_documento', 'id')->where('activo', true),
            ],
            'nro_documento' => ['nullable', 'required_with:tipo_documento_id', 'string', 'max:20', $uniqueDocument],
            'nombres_razon_social' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['tipo_documento_id', 'nro_documento']) || ! $this->filled('tipo_documento_id')) {
                    return;
                }

                $documentType = TipoDocumento::query()->find($this->integer('tipo_documento_id'));
                $documentNumber = $this->string('nro_documento')->toString();

                if ($documentType?->longitud_maxima !== null && Str::length($documentNumber) > $documentType->longitud_maxima) {
                    $validator->errors()->add(
                        'nro_documento',
                        "El número de documento no puede superar {$documentType->longitud_maxima} caracteres para {$documentType->codigo}.",
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_documento_id.required_with' => 'Selecciona el tipo de documento.',
            'tipo_documento_id.exists' => 'El tipo de documento seleccionado no está disponible.',
            'nro_documento.required_with' => 'Ingresa el número de documento.',
            'nro_documento.max' => 'El número de documento no puede superar los 20 caracteres.',
            'nro_documento.unique' => 'Ya existe un cliente con ese tipo y número de documento.',
            'nombres_razon_social.required' => 'Ingresa el nombre o razón social del cliente.',
            'nombres_razon_social.max' => 'El nombre o razón social no puede superar los 150 caracteres.',
            'telefono.regex' => 'El teléfono debe contener exactamente 9 dígitos numéricos.',
            'direccion.max' => 'La dirección no puede superar los 255 caracteres.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $hasDocumentType = $this->filled('tipo_documento_id');

        $this->merge([
            'tipo_documento_id' => $hasDocumentType ? $this->integer('tipo_documento_id') : null,
            'nro_documento' => $this->filled('nro_documento')
                ? Str::upper($this->string('nro_documento')->trim()->toString())
                : null,
            'nombres_razon_social' => $this->string('nombres_razon_social')->squish()->toString(),
            'telefono' => $this->filled('telefono') ? $this->string('telefono')->trim()->toString() : null,
            'direccion' => $this->filled('direccion') ? $this->string('direccion')->squish()->toString() : null,
            'observacion' => $this->filled('observacion') ? $this->string('observacion')->squish()->toString() : null,
            'activo' => $this->boolean('activo'),
        ]);
    }
}
