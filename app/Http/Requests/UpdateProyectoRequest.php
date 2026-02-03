<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_proyecto_id' => ['sometimes', 'required', 'exists:tipos_proyecto,id'],
            'nombre_proyecto' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'empresa_cliente' => ['sometimes', 'required', 'string', 'max:200'],
            'fecha_inicio_estimada' => ['nullable', 'date'],
            'fecha_fin_estimada' => ['nullable', 'date', 'after_or_equal:fecha_inicio_estimada'],
            'fecha_inicio_real' => ['nullable', 'date'],
            'fecha_fin_real' => ['nullable', 'date', 'after_or_equal:fecha_inicio_real'],
            'estado_proyecto' => ['nullable', 'string', 'in:planificacion,activo,suspendido,finalizado'],

            // Ubicación
            'ubicacion' => ['nullable', 'array'],
            'ubicacion.departamento_geo_id' => ['sometimes', 'exists:departamentos_geograficos,id'],
            'ubicacion.municipio_id' => ['sometimes', 'exists:municipios,id'],
            'ubicacion.zona' => ['nullable', 'integer'],
            'ubicacion.direccion_completa' => ['sometimes', 'string'],

            // Facturación
            'facturacion' => ['nullable', 'array'],
            'facturacion.tipo_documento_facturacion_id' => ['sometimes', 'exists:tipos_documentos_facturacion,id'],
            'facturacion.nit_cliente' => ['sometimes', 'string', 'max:15'],
            'facturacion.nombre_facturacion' => ['sometimes', 'string', 'max:255'],
            'facturacion.direccion_facturacion' => ['sometimes', 'string'],
            'facturacion.forma_pago' => ['sometimes', 'string'],
            'facturacion.periodicidad_pago_id' => ['sometimes', 'exists:periodicidades_pago,id'],
            'facturacion.dia_pago' => ['nullable', 'integer', 'between:1,31'],
            'facturacion.monto_proyecto_total' => ['nullable', 'numeric'],
            'facturacion.moneda' => ['nullable', 'string', 'max:3'],
        ];
    }
}
