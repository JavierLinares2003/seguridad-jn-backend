<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personal_id' => ['required', 'exists:personal,id'],
            'monto_total' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'tasa_interes' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fecha_prestamo' => ['nullable', 'date'],
            'fecha_primer_pago' => ['nullable', 'date', 'after_or_equal:fecha_prestamo'],
            'cuotas_totales' => ['nullable', 'integer', 'min:1'],
            'monto_cuota' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'personal_id.required' => 'El personal es requerido.',
            'personal_id.exists' => 'El personal seleccionado no existe.',
            'monto_total.required' => 'El monto total es requerido.',
            'monto_total.min' => 'El monto debe ser mayor a 0.',
            'monto_total.max' => 'El monto excede el límite permitido.',
            'tasa_interes.min' => 'La tasa de interés no puede ser negativa.',
            'tasa_interes.max' => 'La tasa de interés no puede exceder 100%.',
            'fecha_primer_pago.after_or_equal' => 'La fecha del primer pago debe ser posterior a la fecha del préstamo.',
            'cuotas_totales.min' => 'Debe haber al menos 1 cuota.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->personal_id) {
                $prestamosActivos = \App\Models\Prestamo::where('personal_id', $this->personal_id)
                    ->where('estado_prestamo', 'activo')
                    ->count();
                
                if ($prestamosActivos > 0) {
                    $validator->errors()->add('personal_id', 
                        'El personal ya tiene un préstamo activo. Debe liquidarlo antes de solicitar uno nuevo.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
