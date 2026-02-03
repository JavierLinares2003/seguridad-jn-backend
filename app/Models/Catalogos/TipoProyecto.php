<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class TipoProyecto extends Model
{
    protected $table = 'tipos_proyecto';

    protected $fillable = [
        'nombre',
        'prefijo_correlativo',
        'descripcion',
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
