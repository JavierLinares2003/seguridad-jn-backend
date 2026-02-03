<?php

namespace App\Http\Requests\Personal;

use App\Models\PersonalReferenciaLaboral;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReferenciaLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_empresa' => ['required', 'string', 'max:200'],
            'puesto_ocupado' => ['required', 'string', 'max:100'],
            'telefono' => ['required', 'string', 'max:15'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'fecha_inicio' => ['required', 'date', 'before_or_equal:today'],
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio', 'before_or_equal:today'],
            'motivo_retiro' => ['nullable', 'string', 'max:500', 'required_with:fecha_fin'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_empresa.required' => 'El nombre de la empresa es obligatorio.',
            'nombre_empresa.max' => 'El nombre de la empresa no puede exceder 200 caracteres.',
            'puesto_ocupado.required' => 'El puesto ocupado es obligatorio.',
            'puesto_ocupado.max' => 'El puesto ocupado no puede exceder 100 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio no puede ser futura.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'fecha_fin.before_or_equal' => 'La fecha de fin no puede ser futura.',
            'motivo_retiro.required_with' => 'Debe indicar el motivo de retiro si especifica fecha de fin.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $personalId = $this->route('personal');
            $referenciaId = $this->route('referencia');

            // Validar que solo exista una referencia sin fecha_fin (trabajo actual)
            if (is_null($this->fecha_fin)) {
                $query = PersonalReferenciaLaboral::where('personal_id', $personalId)
                    ->whereNull('fecha_fin');

                if ($referenciaId) {
                    $query->where('id', '!=', $referenciaId);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'fecha_fin',
                        'Ya existe una referencia laboral marcada como trabajo actual (sin fecha de fin). Debe cerrar esa referencia primero.'
                    );
                }
            }

            // Validar solapamiento de fechas
            if ($this->fecha_inicio) {
                $fechaInicio = Carbon::parse($this->fecha_inicio);
                $fechaFin = $this->fecha_fin ? Carbon::parse($this->fecha_fin) : Carbon::now();

                $query = PersonalReferenciaLaboral::where('personal_id', $personalId)
                    ->where(function ($q) use ($fechaInicio, $fechaFin) {
                        $q->where(function ($subQ) use ($fechaInicio, $fechaFin) {
                            // Nueva referencia empieza durante una existente
                            $subQ->where('fecha_inicio', '<=', $fechaInicio)
                                 ->where(function ($dateQ) use ($fechaInicio) {
                                     $dateQ->where('fecha_fin', '>=', $fechaInicio)
                                           ->orWhereNull('fecha_fin');
                                 });
                        })->orWhere(function ($subQ) use ($fechaInicio, $fechaFin) {
                            // Nueva referencia termina durante una existente
                            $subQ->where('fecha_inicio', '<=', $fechaFin)
                                 ->where(function ($dateQ) use ($fechaFin) {
                                     $dateQ->where('fecha_fin', '>=', $fechaFin)
                                           ->orWhereNull('fecha_fin');
                                 });
                        })->orWhere(function ($subQ) use ($fechaInicio, $fechaFin) {
                            // Nueva referencia contiene completamente una existente
                            $subQ->where('fecha_inicio', '>=', $fechaInicio)
                                 ->where(function ($dateQ) use ($fechaFin) {
                                     $dateQ->where('fecha_fin', '<=', $fechaFin)
                                           ->orWhereNull('fecha_fin');
                                 });
                        });
                    });

                if ($referenciaId) {
                    $query->where('id', '!=', $referenciaId);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'fecha_inicio',
                        'Las fechas se solapan con otra referencia laboral existente.'
                    );
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
