<?php

namespace App\Http\Requests\Personal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PersonalDireccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departamento_geo_id' => ['nullable', 'exists:departamentos_geograficos,id'],
            'municipio_id' => [
                'nullable',
                'exists:municipios,id',
                function ($attribute, $value, $fail) {
                    if ($value && $this->departamento_geo_id) {
                        $municipio = \App\Models\Catalogos\Municipio::find($value);
                        if ($municipio && $municipio->departamento_geo_id != $this->departamento_geo_id) {
                            $fail('El municipio no pertenece al departamento seleccionado.');
                        }
                    }
                },
            ],
            'zona' => ['nullable', 'integer', 'min:1', 'max:25'],
            'direccion_completa' => ['required', 'string', 'max:500'],
            'es_direccion_actual' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'departamento_geo_id.exists' => 'El departamento seleccionado no existe.',
            'municipio_id.exists' => 'El municipio seleccionado no existe.',
            'zona.min' => 'La zona debe ser mínimo 1.',
            'zona.max' => 'La zona debe ser máximo 25.',
            'direccion_completa.required' => 'La dirección completa es obligatoria.',
            'direccion_completa.max' => 'La dirección no puede exceder 500 caracteres.',
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
