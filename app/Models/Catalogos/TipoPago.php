<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class TipoPago extends Model
{
    protected $table = 'tipos_pago';

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
