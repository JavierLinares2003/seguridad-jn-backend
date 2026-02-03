<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalRedSocialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'red_social' => $this->whenLoaded('redSocial', fn () => [
                'id' => $this->redSocial->id,
                'nombre' => $this->redSocial->nombre,
                'icono' => $this->redSocial->icono,
                'url_base' => $this->redSocial->url_base,
            ]),
            'nombre_usuario' => $this->nombre_usuario,
            'url_perfil' => $this->url_perfil,
        ];
    }
}
