<?php

namespace App\Http\Requests\Operacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'turno_id' => [
                'sometimes',
                'integer',
                'exists:turnos,id',
            ],
            'fecha_inicio' => [
                'sometimes',
                'date',
            ],
            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
            ],
            'estado_asignacion' => [
                'sometimes',
                'string',
                'in:activa,finalizada,suspendida',
            ],
            'motivo_suspension' => [
                'nullable',
                'string',
                'max:500',
                'required_if:estado_asignacion,suspendida',
            ],
            'notas' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'turno_id.exists' => 'El turno seleccionado no existe.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'estado_asignacion.in' => 'El estado debe ser: activa, finalizada o suspendida.',
            'motivo_suspension.required_if' => 'Debe especificar el motivo de suspensión.',
            'motivo_suspension.max' => 'El motivo de suspensión no puede exceder 500 caracteres.',
            'notas.max' => 'Las notas no pueden exceder 1000 caracteres.',
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
