<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProyectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_proyecto_id' => ['required', 'exists:tipos_proyecto,id'],
            'nombre_proyecto' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'empresa_cliente' => ['required', 'string', 'max:200'],
            'fecha_inicio_estimada' => ['nullable', 'date'],
            'fecha_fin_estimada' => ['nullable', 'date', 'after_or_equal:fecha_inicio_estimada'],
            'fecha_inicio_real' => ['nullable', 'date'],
            'fecha_fin_real' => ['nullable', 'date', 'after_or_equal:fecha_inicio_real'],
            'estado_proyecto' => ['nullable', 'string', 'in:planificacion,activo,suspendido,finalizado'],

            // Ubicación
            'ubicacion' => ['nullable', 'array'],
            'ubicacion.departamento_geo_id' => ['required_with:ubicacion', 'exists:departamentos_geograficos,id'],
            'ubicacion.municipio_id' => ['required_with:ubicacion', 'exists:municipios,id'],
            'ubicacion.zona' => ['nullable', 'integer'],
            'ubicacion.direccion_completa' => ['required_with:ubicacion', 'string'],

            // Facturación
            'facturacion' => ['nullable', 'array'],
            'facturacion.tipo_documento_facturacion_id' => ['required_with:facturacion', 'exists:tipos_documentos_facturacion,id'],
            'facturacion.nit_cliente' => ['required_with:facturacion', 'string', 'max:15'],
            'facturacion.nombre_facturacion' => ['required_with:facturacion', 'string', 'max:255'],
            'facturacion.direccion_facturacion' => ['required_with:facturacion', 'string'],
            'facturacion.forma_pago' => ['required_with:facturacion', 'string'],
            'facturacion.periodicidad_pago_id' => ['required_with:facturacion', 'exists:periodicidades_pago,id'],
            'facturacion.dia_pago' => ['nullable', 'integer', 'between:1,31'],
            'facturacion.monto_proyecto_total' => ['nullable', 'numeric'],
            'facturacion.moneda' => ['nullable', 'string', 'max:3'],
        ];
    }
}
