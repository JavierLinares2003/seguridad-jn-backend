<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombre_completo,
            'iniciales' => $this->iniciales,
            'dpi' => $this->dpi,
            'nit' => $this->nit,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'numero_igss' => $this->numero_igss,
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
            'edad' => $this->edad,

            // Catálogos
            'estado_civil' => $this->whenLoaded('estadoCivil', fn () => [
                'id' => $this->estadoCivil->id,
                'nombre' => $this->estadoCivil->nombre,
            ]),
            'tipo_sangre' => $this->whenLoaded('tipoSangre', fn () => [
                'id' => $this->tipoSangre->id,
                'nombre' => $this->tipoSangre->nombre,
            ]),
            'sexo' => $this->whenLoaded('sexo', fn () => [
                'id' => $this->sexo->id,
                'nombre' => $this->sexo->nombre,
            ]),
            'tipo_contratacion' => $this->whenLoaded('tipoContratacion', fn () => [
                'id' => $this->tipoContratacion->id,
                'nombre' => $this->tipoContratacion->nombre,
            ]),
            'tipo_pago' => $this->whenLoaded('tipoPago', fn () => [
                'id' => $this->tipoPago->id,
                'nombre' => $this->tipoPago->nombre,
            ]),
            'departamento' => $this->whenLoaded('departamento', fn () => [
                'id' => $this->departamento->id,
                'nombre' => $this->departamento->nombre,
            ]),

            // Datos físicos
            'altura' => $this->altura,
            'peso' => $this->peso,
            'sabe_leer' => $this->sabe_leer,
            'sabe_escribir' => $this->sabe_escribir,
            'es_alergico' => $this->es_alergico,
            'alergias' => $this->alergias,

            // Datos laborales
            'salario_base' => $this->salario_base,
            'puesto' => $this->puesto,

            // Otros
            'observaciones' => $this->observaciones,
            'foto_perfil' => $this->foto_perfil,
            'estado' => $this->estado,

            // Relaciones
            'direccion' => $this->whenLoaded('direccion', fn () => new PersonalDireccionResource($this->direccion)),
            'referencias_laborales' => PersonalReferenciaLaboralResource::collection($this->whenLoaded('referenciasLaborales')),
            'redes_sociales' => PersonalRedSocialResource::collection($this->whenLoaded('redesSociales')),
            'familiares' => PersonalFamiliarResource::collection($this->whenLoaded('familiares')),
            'documentos' => PersonalDocumentoResource::collection($this->whenLoaded('documentos')),

            // Contadores (cuando se necesiten)
            'documentos_count' => $this->when(isset($this->documentos_count), $this->documentos_count),
            'documentos_vencidos_count' => $this->when(isset($this->documentos_vencidos_count), $this->documentos_vencidos_count),

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
