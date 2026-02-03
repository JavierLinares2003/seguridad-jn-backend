<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $table = 'departamentos';

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
