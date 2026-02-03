<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class TipoDocumentoPersonal extends Model
{
    protected $table = 'tipos_documentos_personal';

    protected $fillable = [
        'nombre',
        'requiere_vencimiento',
        'extensiones_permitidas',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'requiere_vencimiento' => 'boolean',
            'extensiones_permitidas' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
