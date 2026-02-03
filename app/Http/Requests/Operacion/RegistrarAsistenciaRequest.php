<?php

namespace App\Http\Requests\Operacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegistrarAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Puede ser un array de asistencias o una sola
            'asistencias' => ['required', 'array', 'min:1'],
            'asistencias.*.personal_asignado_id' => [
                'required',
                'integer',
                'exists:operaciones_personal_asignado,id',
            ],
            'asistencias.*.fecha_asistencia' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'asistencias.*.hora_entrada' => [
                'nullable',
                'date_format:H:i',
                'required_without:asistencias.*.es_descanso',
            ],
            'asistencias.*.hora_salida' => [
                'nullable',
                'date_format:H:i',
            ],
            'asistencias.*.es_descanso' => [
                'nullable',
                'boolean',
            ],
            'asistencias.*.fue_reemplazado' => [
                'nullable',
                'boolean',
            ],
            'asistencias.*.personal_reemplazo_id' => [
                'nullable',
                'integer',
                Rule::exists('personal', 'id')->whereNull('deleted_at'),
                'required_if:asistencias.*.fue_reemplazado,true',
            ],
            'asistencias.*.motivo_reemplazo' => [
                'nullable',
                'string',
                'max:500',
                'required_if:asistencias.*.fue_reemplazado,true',
            ],
            'asistencias.*.observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'asistencias.required' => 'Debe proporcionar al menos un registro de asistencia.',
            'asistencias.array' => 'Las asistencias deben ser un arreglo.',
            'asistencias.min' => 'Debe proporcionar al menos un registro de asistencia.',
            'asistencias.*.personal_asignado_id.required' => 'La asignación es obligatoria.',
            'asistencias.*.personal_asignado_id.exists' => 'La asignación no existe.',
            'asistencias.*.fecha_asistencia.required' => 'La fecha es obligatoria.',
            'asistencias.*.fecha_asistencia.date' => 'La fecha debe ser válida.',
            'asistencias.*.fecha_asistencia.before_or_equal' => 'No puede registrar asistencia para fechas futuras.',
            'asistencias.*.hora_entrada.date_format' => 'La hora de entrada debe estar en formato HH:MM.',
            'asistencias.*.hora_entrada.required_without' => 'Debe indicar hora de entrada o marcar como descanso.',
            'asistencias.*.hora_salida.date_format' => 'La hora de salida debe estar en formato HH:MM.',
            'asistencias.*.personal_reemplazo_id.exists' => 'El personal de reemplazo no existe.',
            'asistencias.*.personal_reemplazo_id.required_if' => 'Debe especificar el personal de reemplazo.',
            'asistencias.*.motivo_reemplazo.required_if' => 'Debe especificar el motivo del reemplazo.',
            'asistencias.*.motivo_reemplazo.max' => 'El motivo no puede exceder 500 caracteres.',
            'asistencias.*.observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
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
