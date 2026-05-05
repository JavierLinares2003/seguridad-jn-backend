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
                // 'required_if:asistencias.*.es_ausente,true',
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
            'asistencias.*.tipo_inasistencia' => [
                'nullable',
                'string',
                Rule::in(['12_horas', '24_horas']),
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
            'asistencias.*.permiso_reposicion_id' => [
                'nullable',
                'integer',
                'exists:personal_permisos,id',
            ],
            'asistencias.*.horas_reposicion' => [
                'nullable',
                'numeric',
                'min:0.5',
                'max:24',
                'required_with:asistencias.*.permiso_reposicion_id',
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
            'asistencias.*.motivo_ausencia_id.exists' => 'El motivo de ausencia no existe.',
            'asistencias.*.motivo_ausencia_id.required_if' => 'Debe especificar el motivo de ausencia.',
            'asistencias.*.tipo_ausencia.required_if' => 'Debe especificar si la ausencia es justificada o injustificada.',
            'asistencias.*.tipo_ausencia.in' => 'El tipo de ausencia debe ser justificada o injustificada.',
            'asistencias.*.tipo_inasistencia.required_if' => 'Debe especificar el tipo de inasistencia (12_horas o 24_horas).',
            'asistencias.*.tipo_inasistencia.in' => 'El tipo de inasistencia debe ser 12_horas o 24_horas.',
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
