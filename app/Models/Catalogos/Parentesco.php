<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class Parentesco extends Model
{
    protected $table = 'catalogo_parentescos';

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
