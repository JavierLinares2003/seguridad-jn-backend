<?php

namespace App\Http\Requests\Proyecto;

use App\Models\Catalogos\TipoDocumentoProyecto;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'tipo_documento_proyecto_id' => ['required', 'exists:tipos_documentos_proyecto,id'],
            'archivo' => ['required', 'file', 'max:10240'], // 10MB máximo
            'nombre_documento' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'fecha_vencimiento' => ['nullable', 'date', 'after:today'],
            'dias_alerta_vencimiento' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'tipo_documento_proyecto_id.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento_proyecto_id.exists' => 'El tipo de documento seleccionado no existe.',
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'Debe ser un archivo válido.',
            'archivo.max' => 'El archivo no puede exceder 10MB.',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'dias_alerta_vencimiento.min' => 'Los días de alerta deben ser al menos 1.',
            'dias_alerta_vencimiento.max' => 'Los días de alerta no pueden exceder 365.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $tipoDocumentoId = $this->tipo_documento_proyecto_id;

            if ($tipoDocumentoId && $this->hasFile('archivo')) {
                $tipoDocumento = TipoDocumentoProyecto::find($tipoDocumentoId);

                if ($tipoDocumento) {
                    // Validar extensiones permitidas
                    $archivo = $this->file('archivo');
                    $extension = strtolower($archivo->getClientOriginalExtension());
                    $extensionesPermitidas = $tipoDocumento->extensiones_permitidas ?? [];

                    if (!empty($extensionesPermitidas) && !in_array($extension, $extensionesPermitidas)) {
                        $validator->errors()->add(
                            'archivo',
                            'La extensión del archivo no es válida para este tipo de documento. Extensiones permitidas: ' . implode(', ', $extensionesPermitidas)
                        );
                    }

                    // Validar fecha de vencimiento requerida
                    if ($tipoDocumento->requiere_vencimiento && !$this->fecha_vencimiento) {
                        $validator->errors()->add(
                            'fecha_vencimiento',
                            'Este tipo de documento requiere fecha de vencimiento.'
                        );
                    }
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
