<?php

namespace App\Http\Requests\Personal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Datos personales
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'dpi' => ['required', 'string', 'size:13', 'unique:personal,dpi'],
            'nit' => ['nullable', 'string', 'max:15', 'unique:personal,nit'],
            'email' => ['required', 'email', 'max:150', 'unique:personal,email'],
            'telefono' => ['required', 'string', 'max:15'],
            'numero_igss' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'estado_civil_id' => ['nullable', 'exists:estados_civiles,id'],
            'sexo_id' => ['nullable', 'exists:sexos,id'],

            // Datos físicos
            'altura' => ['required', 'numeric', 'min:0.5', 'max:2.5'],
            'tipo_sangre_id' => ['nullable', 'exists:tipos_sangre,id'],
            'peso' => ['required', 'numeric', 'min:50', 'max:500'],
            'sabe_leer' => ['boolean'],
            'sabe_escribir' => ['boolean'],
            'es_alergico' => ['boolean'],
            'alergias' => ['nullable', 'string', 'required_if:es_alergico,true'],

            // Datos laborales
            'tipo_contratacion_id' => ['nullable', 'exists:tipos_contratacion,id'],
            'salario_base' => ['required', 'numeric', 'min:0'],
            'tipo_pago_id' => ['nullable', 'exists:tipos_pago,id'],
            'puesto' => ['required', 'string', 'max:100'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],

            // Otros
            'observaciones' => ['nullable', 'string'],
            'foto_perfil' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'in:activo,inactivo,suspendido'],

            // Dirección (opcional)
            'direccion' => ['nullable', 'array'],
            'direccion.departamento_geo_id' => ['nullable', 'exists:departamentos_geograficos,id'],
            'direccion.municipio_id' => ['nullable', 'exists:municipios,id'],
            'direccion.zona' => ['nullable', 'integer', 'min:1', 'max:25'],
            'direccion.direccion_completa' => ['required_with:direccion', 'string'],

            // Referencias laborales (opcional)
            'referencias_laborales' => ['nullable', 'array'],
            'referencias_laborales.*.nombre_empresa' => ['required', 'string', 'max:200'],
            'referencias_laborales.*.puesto_ocupado' => ['required', 'string', 'max:100'],
            'referencias_laborales.*.telefono' => ['required', 'string', 'max:15'],
            'referencias_laborales.*.direccion' => ['nullable', 'string'],
            'referencias_laborales.*.fecha_inicio' => ['required', 'date'],
            'referencias_laborales.*.fecha_fin' => ['nullable', 'date', 'after:referencias_laborales.*.fecha_inicio'],
            'referencias_laborales.*.motivo_retiro' => ['nullable', 'string'],

            // Redes sociales (opcional)
            'redes_sociales' => ['nullable', 'array'],
            'redes_sociales.*.red_social_id' => ['required', 'exists:catalogo_redes_sociales,id'],
            'redes_sociales.*.nombre_usuario' => ['required', 'string', 'max:100'],
            'redes_sociales.*.url_perfil' => ['nullable', 'url', 'max:255'],

            // Familiares (opcional)
            'familiares' => ['nullable', 'array'],
            'familiares.*.parentesco_id' => ['required', 'exists:catalogo_parentescos,id'],
            'familiares.*.nombre_completo' => ['required', 'string', 'max:200'],
            'familiares.*.telefono' => ['required', 'string', 'max:15'],
            'familiares.*.es_contacto_emergencia' => ['boolean'],
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
            'altura.min' => 'La altura mínima es 0.5 metros.',
            'altura.max' => 'La altura máxima es 2.5 metros.',
            'peso.required' => 'El peso es obligatorio.',
            'peso.min' => 'El peso mínimo es 50 libras.',
            'peso.max' => 'El peso máximo es 500 libras.',
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
