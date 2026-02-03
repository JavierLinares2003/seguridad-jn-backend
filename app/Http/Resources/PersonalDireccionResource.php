<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalDireccionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departamento_geografico' => $this->whenLoaded('departamentoGeografico', fn () => [
                'id' => $this->departamentoGeografico->id,
                'codigo' => $this->departamentoGeografico->codigo,
                'nombre' => $this->departamentoGeografico->nombre,
            ]),
            'municipio' => $this->whenLoaded('municipio', fn () => [
                'id' => $this->municipio->id,
                'codigo' => $this->municipio->codigo,
                'nombre' => $this->municipio->nombre,
            ]),
            'zona' => $this->zona,
            'direccion_completa' => $this->direccion_completa,
            'es_direccion_actual' => $this->es_direccion_actual,
        ];
    }
}
