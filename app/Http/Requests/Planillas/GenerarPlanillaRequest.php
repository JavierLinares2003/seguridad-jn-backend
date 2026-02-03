<?php

namespace App\Http\Requests\Planillas;

use Illuminate\Foundation\Http\FormRequest;

class GenerarPlanillaRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'periodo_inicio' => 'required|date',
            'periodo_fin' => 'required|date|after:periodo_inicio',
            'proyecto_id' => 'nullable|exists:proyectos,id',
            'observaciones' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'periodo_inicio.required' => 'La fecha de inicio del período es requerida',
            'periodo_inicio.date' => 'La fecha de inicio debe ser una fecha válida',
            'periodo_fin.required' => 'La fecha de fin del período es requerida',
            'periodo_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'periodo_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'proyecto_id.exists' => 'El proyecto seleccionado no existe',
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres',
        ];
    }
}
