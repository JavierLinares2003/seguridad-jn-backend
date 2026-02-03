<?php

namespace App\Http\Requests\Operacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personal_id' => [
                'required',
                'integer',
                Rule::exists('personal', 'id')->whereNull('deleted_at'),
            ],
            'proyecto_id' => [
                'nullable',
                'integer',
                Rule::exists('proyectos', 'id')->whereNull('deleted_at'),
            ],
            'configuracion_puesto_id' => [
                'nullable',
                'integer',
                'exists:proyectos_configuracion_personal,id',
            ],
            'turno_id' => [
                'required',
                'integer',
                'exists:turnos,id',
            ],
            'fecha_inicio' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
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
            'personal_id.required' => 'El personal es obligatorio.',
            'personal_id.exists' => 'El personal seleccionado no existe o está eliminado.',
            'proyecto_id.required' => 'El proyecto es obligatorio.',
            'proyecto_id.exists' => 'El proyecto seleccionado no existe o está eliminado.',
            'configuracion_puesto_id.required' => 'La configuración del puesto es obligatoria.',
            'configuracion_puesto_id.exists' => 'La configuración del puesto no existe.',
            'turno_id.required' => 'El turno es obligatorio.',
            'turno_id.exists' => 'El turno seleccionado no existe.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
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
