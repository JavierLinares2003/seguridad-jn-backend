<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalHistorialSalarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'salario_anterior' => $this->salario_anterior,
            'salario_nuevo'    => $this->salario_nuevo,
            'diferencia'       => (float) $this->salario_nuevo - (float) $this->salario_anterior,
            'fecha_cambio'     => $this->fecha_cambio?->format('Y-m-d'),
            'motivo'           => $this->motivo,
            'cambiado_por'     => $this->whenLoaded('cambiadoPor', fn () => [
                'id'   => $this->cambiadoPor->id,
                'name' => $this->cambiadoPor->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
