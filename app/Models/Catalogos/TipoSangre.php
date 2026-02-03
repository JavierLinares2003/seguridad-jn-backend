<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class TipoSangre extends Model
{
    protected $table = 'tipos_sangre';

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
