<?php

namespace App\Http\Requests\Personal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdatePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Remove empty arrays or arrays with empty objects to avoid validation errors
        $data = $this->all();
        
        // Helper function to check if array contains only empty objects
        $isEmptyArray = function($value) {
            if (!is_array($value) || empty($value)) {
                return true;
            }
            foreach ($value as $item) {
                if (!is_array($item) || !empty(array_filter($item, fn($v) => $v !== null && $v !== ''))) {
                    return false;
                }
            }
            return true;
        };
        
        if (isset($data['redes_sociales']) && $isEmptyArray($data['redes_sociales'])) {
            unset($data['redes_sociales']);
        }
        
        if (isset($data['familiares']) && $isEmptyArray($data['familiares'])) {
            unset($data['familiares']);
        }
        
        if (isset($data['referencias_laborales']) && $isEmptyArray($data['referencias_laborales'])) {
            unset($data['referencias_laborales']);
        }
        
        $this->replace($data);
    }

    public function rules(): array
    {
        $personalId = $this->route('personal');

        return [
            // Datos personales
            'nombres' => ['sometimes', 'required', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'required', 'string', 'max:100'],
            'dpi' => ['sometimes', 'required', 'string', 'size:13', Rule::unique('personal')->ignore($personalId)],
            'nit' => ['nullable', 'string', 'max:15', Rule::unique('personal')->ignore($personalId)],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('personal')->ignore($personalId)],
            'telefono' => ['sometimes', 'required', 'string', 'max:15'],
            'numero_igss' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['sometimes', 'required', 'date', 'before:today'],
            'estado_civil_id' => ['nullable', 'exists:estados_civiles,id'],
            'sexo_id' => ['nullable', 'exists:sexos,id'],

            // Datos físicos
            'altura' => ['sometimes', 'required', 'numeric', 'min:0.5', 'max:2.5'],
            'tipo_sangre_id' => ['nullable', 'exists:tipos_sangre,id'],
            'peso' => ['sometimes', 'required', 'numeric', 'min:50', 'max:500'],
            'sabe_leer' => ['boolean'],
            'sabe_escribir' => ['boolean'],
            'es_alergico' => ['boolean'],
            'alergias' => ['nullable', 'string', 'required_if:es_alergico,true'],

            // Datos laborales
            'tipo_contratacion_id' => ['nullable', 'exists:tipos_contratacion,id'],
            'salario_base' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tipo_pago_id' => ['nullable', 'exists:tipos_pago,id'],
            'puesto' => ['sometimes', 'required', 'string', 'max:100'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],

            // Otros
            'observaciones' => ['nullable', 'string'],
            'foto_perfil' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'in:activo,inactivo,suspendido'],

            // Dirección
            'direccion' => ['nullable', 'array'],
            'direccion.departamento_geo_id' => ['nullable', 'exists:departamentos_geograficos,id'],
            'direccion.municipio_id' => ['nullable', 'exists:municipios,id'],
            'direccion.zona' => ['nullable', 'integer', 'min:1', 'max:25'],
            'direccion.direccion_completa' => ['required_with:direccion', 'string'],

            // Referencias laborales
            'referencias_laborales' => ['nullable', 'array'],
            'referencias_laborales.*.id' => ['nullable', 'exists:personal_referencias_laborales,id'],
            'referencias_laborales.*.nombre_empresa' => ['required', 'string', 'max:200'],
            'referencias_laborales.*.puesto_ocupado' => ['required', 'string', 'max:100'],
            'referencias_laborales.*.telefono' => ['required', 'string', 'max:15'],
            'referencias_laborales.*.direccion' => ['nullable', 'string'],
            'referencias_laborales.*.fecha_inicio' => ['required', 'date'],
            'referencias_laborales.*.fecha_fin' => ['nullable', 'date', 'after:referencias_laborales.*.fecha_inicio'],
            'referencias_laborales.*.motivo_retiro' => ['nullable', 'string'],
            'referencias_laborales.*._delete' => ['nullable', 'boolean'],

            // Redes sociales
            'redes_sociales' => ['nullable', 'array'],
            'redes_sociales.*.id' => ['nullable', 'exists:personal_redes_sociales,id'],
            'redes_sociales.*.red_social_id' => ['required_with:redes_sociales.*', 'exists:catalogo_redes_sociales,id'],
            'redes_sociales.*.nombre_usuario' => ['required_with:redes_sociales.*', 'string', 'max:100'],
            'redes_sociales.*.url_perfil' => ['nullable', 'url', 'max:255'],
            'redes_sociales.*._delete' => ['nullable', 'boolean'],

            // Familiares
            'familiares' => ['nullable', 'array'],
            'familiares.*.id' => ['nullable', 'exists:personal_familiares,id'],
            'familiares.*.parentesco_id' => ['required_with:familiares.*', 'exists:catalogo_parentescos,id'],
            'familiares.*.nombre_completo' => ['required_with:familiares.*', 'string', 'max:200'],
            'familiares.*.telefono' => ['required_with:familiares.*', 'string', 'max:15'],
            'familiares.*.es_contacto_emergencia' => ['boolean'],
            'familiares.*._delete' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombres.required' => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'dpi.required' => 'El DPI es obligatorio.',
            'dpi.size' => 'El DPI debe tener exactamente 13 dígitos.',
            'dpi.unique' => 'Este DPI ya está registrado.',
            'nit.unique' => 'Este NIT ya está registrado.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe ser válido.',
            'email.unique' => 'Este email ya está registrado.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'altura.required' => 'La altura es obligatoria.',
            'peso.required' => 'El peso es obligatorio.',
            'alergias.required_if' => 'Debe especificar las alergias si marcó que es alérgico.',
            'salario_base.required' => 'El salario base es obligatorio.',
            'puesto.required' => 'El puesto es obligatorio.',
            'estado.in' => 'El estado debe ser: activo, inactivo o suspendido.',
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
