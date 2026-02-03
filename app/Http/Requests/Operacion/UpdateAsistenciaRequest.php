<?php

namespace App\Http\Requests\Operacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hora_entrada' => [
                'nullable',
                'date_format:H:i',
            ],
            'hora_salida' => [
                'nullable',
                'date_format:H:i',
            ],
            'es_descanso' => [
                'nullable',
                'boolean',
            ],
            'fue_reemplazado' => [
                'nullable',
                'boolean',
            ],
            'personal_reemplazo_id' => [
                'nullable',
                'integer',
                Rule::exists('personal', 'id')->whereNull('deleted_at'),
                'required_if:fue_reemplazado,true',
            ],
            'motivo_reemplazo' => [
                'nullable',
                'string',
                'max:500',
                'required_if:fue_reemplazado,true',
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'hora_entrada.date_format' => 'La hora de entrada debe estar en formato HH:MM.',
            'hora_salida.date_format' => 'La hora de salida debe estar en formato HH:MM.',
            'personal_reemplazo_id.exists' => 'El personal de reemplazo no existe.',
            'personal_reemplazo_id.required_if' => 'Debe especificar el personal de reemplazo.',
            'motivo_reemplazo.required_if' => 'Debe especificar el motivo del reemplazo.',
            'motivo_reemplazo.max' => 'El motivo no puede exceder 500 caracteres.',
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
        ];
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
