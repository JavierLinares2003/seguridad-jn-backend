<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $verSensible = true;

        return [
            // ── Información general (visible para todos los roles) ──────────
            'id'             => $this->id,
            'nombres'        => $this->nombres,
            'apellidos'      => $this->apellidos,
            'nombre_completo'=> $this->nombre_completo,
            'iniciales'      => $this->iniciales,
            'dpi'            => $this->dpi,
            'email'          => $this->email,
            'telefono'       => $this->telefono,
            'foto_perfil'    => $this->foto_perfil,
            'estado'         => $this->estado,
            'puesto'         => $this->puesto,
            'fecha_inicio'   => $this->fecha_inicio?->format('Y-m-d'),
            'departamento'   => $this->whenLoaded('departamento', fn () => [
                'id'     => $this->departamento->id,
                'nombre' => $this->departamento->nombre,
            ]),
            'tipo_contratacion' => $this->whenLoaded('tipoContratacion', fn () => [
                'id'     => $this->tipoContratacion->id,
                'nombre' => $this->tipoContratacion->nombre,
            ]),

            // ── Datos sensibles (requiere permiso view-personal-sensible) ───
            'nit'                => $this->when($verSensible, $this->nit),
            'numero_igss'        => $this->when($verSensible, $this->numero_igss),
            'fecha_nacimiento'   => $this->when($verSensible, $this->fecha_nacimiento?->format('Y-m-d')),
            'edad'               => $this->when($verSensible, $this->edad),
            'estado_civil'       => $this->when($verSensible, $this->whenLoaded('estadoCivil', fn () => [
                'id'     => $this->estadoCivil->id,
                'nombre' => $this->estadoCivil->nombre,
            ])),
            'tipo_sangre'        => $this->when($verSensible, $this->whenLoaded('tipoSangre', fn () => [
                'id'     => $this->tipoSangre->id,
                'nombre' => $this->tipoSangre->nombre,
            ])),
            'sexo'               => $this->when($verSensible, $this->whenLoaded('sexo', fn () => [
                'id'     => $this->sexo->id,
                'nombre' => $this->sexo->nombre,
            ])),
            'nivel_estudio'      => $this->when($verSensible, $this->whenLoaded('nivelEstudio', fn () => [
                'id'     => $this->nivelEstudio->id,
                'nombre' => $this->nivelEstudio->nombre,
            ])),
            'altura'                  => $this->when($verSensible, $this->altura),
            'peso'                    => $this->when($verSensible, $this->peso),
            'sabe_leer'               => $this->when($verSensible, $this->sabe_leer),
            'sabe_escribir'           => $this->when($verSensible, $this->sabe_escribir),
            'sabe_usar_computadora'   => $this->when($verSensible, $this->sabe_usar_computadora),
            'es_alergico'             => $this->when($verSensible, $this->es_alergico),
            'alergias'                => $this->when($verSensible, $this->alergias),
            'salario_base'            => $this->when($verSensible, $this->salario_base),
            'tiene_igss'              => $this->when($verSensible, $this->tiene_igss),
            'tiene_prestaciones'      => $this->when($verSensible, $this->tiene_prestaciones),
            'tiene_bono14'            => $this->when($verSensible, $this->tiene_bono14),
            'vacaciones_saldo_inicial'=> $this->when($verSensible, $this->vacaciones_saldo_inicial),
            'tipo_pago'               => $this->when($verSensible, $this->whenLoaded('tipoPago', fn () => [
                'id'     => $this->tipoPago->id,
                'nombre' => $this->tipoPago->nombre,
            ])),
            'banco'          => $this->when($verSensible, $this->banco),
            'tipo_cuenta'    => $this->when($verSensible, $this->tipo_cuenta),
            'numero_cuenta'  => $this->when($verSensible, $this->numero_cuenta),
            'nombre_cuenta'  => $this->when($verSensible, $this->nombre_cuenta),
            'observaciones'  => $this->when($verSensible, $this->observaciones),

            // Relaciones sensibles
            'direccion'            => $this->when($verSensible, $this->whenLoaded('direccion', fn () => new PersonalDireccionResource($this->direccion))),
            'referencias_laborales'=> $this->when($verSensible, PersonalReferenciaLaboralResource::collection($this->whenLoaded('referenciasLaborales'))),
            'redes_sociales'       => $this->when($verSensible, PersonalRedSocialResource::collection($this->whenLoaded('redesSociales'))),
            'familiares'           => $this->when($verSensible, PersonalFamiliarResource::collection($this->whenLoaded('familiares'))),
            'documentos'           => $this->when($verSensible, PersonalDocumentoResource::collection($this->whenLoaded('documentos'))),

            // Contadores
            'documentos_count'          => $this->when($verSensible && isset($this->documentos_count), $this->documentos_count ?? null),
            'documentos_vencidos_count' => $this->when($verSensible && isset($this->documentos_vencidos_count), $this->documentos_vencidos_count ?? null),

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
