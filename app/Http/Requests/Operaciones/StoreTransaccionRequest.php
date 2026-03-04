<?php

namespace App\Http\Requests\Operaciones;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTransaccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personal_id' => ['required', 'exists:personal,id'],
            'asistencia_id' => ['nullable', 'exists:operaciones_asistencia,id'],
            'tipo_transaccion' => [
                'required',
                Rule::in(['multa', 'uniforme', 'anticipo', 'prestamo', 'abono_prestamo', 'antecedentes', 'otro_descuento'])
            ],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'fecha_transaccion' => ['nullable', 'date'],
            'es_descuento' => ['nullable', 'boolean'],
            'estado_transaccion' => ['nullable', Rule::in(['pendiente', 'aplicado', 'cancelado'])],
            // Para abonos a préstamo, el prestamo_id se asigna automáticamente al préstamo activo del personal
            'prestamo_id' => ['nullable', 'exists:operaciones_prestamos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'personal_id.required' => 'El personal es requerido.',
            'personal_id.exists' => 'El personal seleccionado no existe.',
            'tipo_transaccion.required' => 'El tipo de transacción es requerido.',
            'tipo_transaccion.in' => 'El tipo de transacción no es válido.',
            'monto.required' => 'El monto es requerido.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'monto.max' => 'El monto excede el límite permitido.',
            'descripcion.required' => 'La descripción es requerida.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'prestamo_id.exists' => 'El préstamo seleccionado no existe.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar abonos a préstamos
            if ($this->tipo_transaccion === 'abono_prestamo') {
                // Buscar el préstamo activo del personal automáticamente
                $prestamoActivo = \App\Models\Prestamo::where('personal_id', $this->personal_id)
                    ->where('estado_prestamo', 'activo')
                    ->first();

                if (!$prestamoActivo) {
                    $validator->errors()->add('personal_id',
                        'El personal no tiene un préstamo activo al cual realizar el abono.');
                    return;
                }

                // Establecer automáticamente el prestamo_id
                $this->merge(['prestamo_id' => $prestamoActivo->id]);

                // Validar que el monto no exceda el saldo pendiente
                if ($this->monto > $prestamoActivo->saldo_pendiente) {
                    $validator->errors()->add('monto',
                        'El monto del abono no puede exceder el saldo pendiente del préstamo (Q' .
                        number_format($prestamoActivo->saldo_pendiente, 2) . ').');
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
