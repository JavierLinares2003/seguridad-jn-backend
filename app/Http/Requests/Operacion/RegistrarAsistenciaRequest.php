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

            // Debe tener personal_asignado_id O personal_id (uno de los dos)
            'asistencias.*.personal_asignado_id' => [
                'nullable',
                'integer',
                'exists:operaciones_personal_asignado,id',
                'required_without:asistencias.*.personal_id',
            ],
            'asistencias.*.personal_id' => [
                'nullable',
                'integer',
                Rule::exists('personal', 'id')->whereNull('deleted_at'),
                'required_without:asistencias.*.personal_asignado_id',
            ],

            'asistencias.*.fecha_asistencia' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'asistencias.*.hora_entrada' => [
                'nullable',
                'date_format:H:i',
                'required_without_all:asistencias.*.es_descanso,asistencias.*.es_ausente',
            ],
            'asistencias.*.hora_salida' => [
                'nullable',
                'date_format:H:i',
            ],
            'asistencias.*.es_descanso' => [
                'nullable',
                'boolean',
            ],
            'asistencias.*.es_ausente' => [
                'nullable',
                'boolean',
            ],
            'asistencias.*.motivo_ausencia_id' => [
                'nullable',
                'integer',
                'exists:catalogo_motivos_ausencia,id',
                'required_if:asistencias.*.es_ausente,true',
            ],
            'asistencias.*.descripcion_ausencia' => [
                'nullable',
                'string',
                'max:500',
            ],
            'asistencias.*.tipo_ausencia' => [
                'nullable',
                'string',
                Rule::in(['justificada', 'injustificada']),
                'required_if:asistencias.*.es_ausente,true',
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
            'asistencias.*.personal_asignado_id.required_without' => 'Debe proporcionar una asignación o un personal.',
            'asistencias.*.personal_asignado_id.exists' => 'La asignación no existe.',
            'asistencias.*.personal_id.required_without' => 'Debe proporcionar un personal o una asignación.',
            'asistencias.*.personal_id.exists' => 'El personal no existe o está eliminado.',
            'asistencias.*.fecha_asistencia.required' => 'La fecha es obligatoria.',
            'asistencias.*.fecha_asistencia.date' => 'La fecha debe ser válida.',
            'asistencias.*.fecha_asistencia.before_or_equal' => 'No puede registrar asistencia para fechas futuras.',
            'asistencias.*.hora_entrada.date_format' => 'La hora de entrada debe estar en formato HH:MM.',
            'asistencias.*.hora_entrada.required_without_all' => 'Debe indicar hora de entrada, marcar como descanso o marcar como ausente.',
            'asistencias.*.hora_salida.date_format' => 'La hora de salida debe estar en formato HH:MM.',
            'asistencias.*.motivo_ausencia_id.exists' => 'El motivo de ausencia no existe.',
            'asistencias.*.motivo_ausencia_id.required_if' => 'Debe especificar el motivo de ausencia.',
            'asistencias.*.tipo_ausencia.required_if' => 'Debe especificar si la ausencia es justificada o injustificada.',
            'asistencias.*.tipo_ausencia.in' => 'El tipo de ausencia debe ser justificada o injustificada.',
            'asistencias.*.descripcion_ausencia.max' => 'La descripción de ausencia no puede exceder 500 caracteres.',
            'asistencias.*.personal_reemplazo_id.exists' => 'El personal de reemplazo no existe.',
            'asistencias.*.personal_reemplazo_id.required_if' => 'Debe especificar el personal de reemplazo.',
            'asistencias.*.motivo_reemplazo.max' => 'El motivo de reemplazo no puede exceder 500 caracteres.',
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
