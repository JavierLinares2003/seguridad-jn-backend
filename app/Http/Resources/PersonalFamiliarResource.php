<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalFamiliarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentesco' => $this->whenLoaded('parentesco', fn () => [
                'id' => $this->parentesco->id,
                'nombre' => $this->parentesco->nombre,
            ]),
            'nombre_completo' => $this->nombre_completo,
            'telefono' => $this->telefono,
            'es_contacto_emergencia' => $this->es_contacto_emergencia,
        ];
    }
}
