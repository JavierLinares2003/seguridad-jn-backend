<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PersonalDocumentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => $this->whenLoaded('tipoDocumento', fn () => [
                'id' => $this->tipoDocumento->id,
                'nombre' => $this->tipoDocumento->nombre,
                'requiere_vencimiento' => $this->tipoDocumento->requiere_vencimiento,
            ]),
            'nombre_documento' => $this->nombre_documento,
            'descripcion' => $this->descripcion,
            'nombre_archivo_original' => $this->nombre_archivo_original,
            'ruta_archivo' => $this->ruta_archivo,
            'url' => $this->ruta_archivo ? Storage::url($this->ruta_archivo) : null,
            'extension' => $this->extension,
            'tamanio_kb' => $this->tamanio_kb,
            'tamanio_formateado' => $this->getTamanioFormateado(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'fecha_subida' => $this->fecha_subida?->format('Y-m-d H:i:s'),
            'subido_por' => $this->whenLoaded('subidoPor', fn () => [
                'id' => $this->subidoPor->id,
                'name' => $this->subidoPor->name,
            ]),
            'estado_documento' => $this->estado_documento,
            'dias_alerta_vencimiento' => $this->dias_alerta_vencimiento,
        ];
    }
}
