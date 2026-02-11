<?php

namespace App\Http\Requests\Personal;

use App\Models\PersonalRedSocial;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RedSocialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->url_perfil && !preg_match('/^https?:\/\//', $this->url_perfil)) {
            $this->merge([
                'url_perfil' => 'https://' . $this->url_perfil,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'red_social_id' => ['required', 'exists:catalogo_redes_sociales,id'],
            'nombre_usuario' => ['required', 'string', 'max:100'],
            'url_perfil' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'red_social_id.required' => 'La red social es obligatoria.',
            'red_social_id.exists' => 'La red social seleccionada no existe.',
            'nombre_usuario.required' => 'El nombre de usuario es obligatorio.',
            'nombre_usuario.max' => 'El nombre de usuario no puede exceder 100 caracteres.',
            'url_perfil.url' => 'La URL del perfil debe ser válida.',
            'url_perfil.max' => 'La URL del perfil no puede exceder 255 caracteres.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $personalId = $this->route('personal');
            $redSocialPersonalId = $this->route('redSocial');
            $redSocialId = $this->red_social_id;

            if ($redSocialId) {
                $query = PersonalRedSocial::where('personal_id', $personalId)
                    ->where('red_social_id', $redSocialId);

                // Si es update, excluir el registro actual
                if ($redSocialPersonalId) {
                    $query->where('id', '!=', $redSocialPersonalId);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'red_social_id',
                        'Esta red social ya está registrada para este personal.'
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
