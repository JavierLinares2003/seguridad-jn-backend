<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class NivelEstudio extends Model
{
    protected $table = 'niveles_estudio';

    protected $fillable = [
        'nombre',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden');
    }
}
