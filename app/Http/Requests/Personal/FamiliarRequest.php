<?php

namespace App\Http\Requests\Personal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FamiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parentesco_id' => ['required', 'exists:catalogo_parentescos,id'],
            'nombre_completo' => ['required', 'string', 'max:200'],
            'telefono' => ['required', 'string', 'max:15'],
            'es_contacto_emergencia' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'parentesco_id.required' => 'El parentesco es obligatorio.',
            'parentesco_id.exists' => 'El parentesco seleccionado no existe.',
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'nombre_completo.max' => 'El nombre completo no puede exceder 200 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.max' => 'El teléfono no puede exceder 15 caracteres.',
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
