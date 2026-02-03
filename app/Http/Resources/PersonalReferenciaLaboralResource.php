<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalReferenciaLaboralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_empresa' => $this->nombre_empresa,
            'puesto_ocupado' => $this->puesto_ocupado,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'motivo_retiro' => $this->motivo_retiro,
        ];
    }
}
