<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class RedSocial extends Model
{
    protected $table = 'catalogo_redes_sociales';

    protected $fillable = [
        'nombre',
        'icono',
        'url_base',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
