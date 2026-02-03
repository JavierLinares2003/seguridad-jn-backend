<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class TipoContratacion extends Model
{
    protected $table = 'tipos_contratacion';

    protected $fillable = [
        'nombre',
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
