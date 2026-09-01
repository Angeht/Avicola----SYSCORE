<?php

namespace App\Http\Requests;

use App\Models\ConfiguracionEmpresa;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateConfiguracionEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Usuario && $user->tienePermiso('CONFIGURACION_EMPRESA_GESTIONAR');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $currentDocumentTypeId = ConfiguracionEmpresa::query()->value('tipo_documento_id');

        return [
            'razon_social' => ['required', 'string', 'max:150'],
            'nombre_comercial' => ['required', 'string', 'max:150'],
            'tipo_documento_id' => [
                'nullable',
                'required_with:nro_documento',
                Rule::exists('tipos_documento', 'id')->where(
                    function (Builder $query) use ($currentDocumentTypeId): Builder {
                        return $query->where('activo', true)
                            ->when(
                                $currentDocumentTypeId !== null,
                                fn (Builder $query): Builder => $query->orWhere('id', $currentDocumentTypeId),
                            );
                    },
                ),
            ],
            'nro_documento' => ['nullable', 'required_with:tipo_documento_id', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'mensaje_ticket' => ['nullable', 'string', 'max:255'],
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
            'razon_social.required' => 'Ingresa la razón social de la empresa.',
            'razon_social.max' => 'La razón social no puede superar los 150 caracteres.',
            'nombre_comercial.required' => 'Ingresa el nombre comercial de la empresa.',
            'nombre_comercial.max' => 'El nombre comercial no puede superar los 150 caracteres.',
            'tipo_documento_id.required_with' => 'Selecciona el tipo de documento.',
            'tipo_documento_id.exists' => 'El tipo de documento seleccionado no está disponible.',
            'nro_documento.required_with' => 'Ingresa el número de documento.',
            'nro_documento.max' => 'El número de documento no puede superar los 20 caracteres.',
            'direccion.max' => 'La dirección no puede superar los 255 caracteres.',
            'telefono.regex' => 'El teléfono debe contener exactamente 9 dígitos numéricos.',
            'mensaje_ticket.max' => 'El mensaje del comprobante no puede superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'razon_social' => Str::upper($this->string('razon_social')->squish()->toString()),
            'nombre_comercial' => Str::upper($this->string('nombre_comercial')->squish()->toString()),
            'tipo_documento_id' => $this->filled('tipo_documento_id') ? $this->integer('tipo_documento_id') : null,
            'nro_documento' => $this->filled('nro_documento')
                ? Str::upper($this->string('nro_documento')->trim()->toString())
                : null,
            'direccion' => $this->filled('direccion') ? $this->string('direccion')->squish()->toString() : null,
            'telefono' => $this->filled('telefono') ? $this->string('telefono')->trim()->toString() : null,
            'mensaje_ticket' => $this->filled('mensaje_ticket') ? $this->string('mensaje_ticket')->squish()->toString() : null,
        ]);
    }
}
