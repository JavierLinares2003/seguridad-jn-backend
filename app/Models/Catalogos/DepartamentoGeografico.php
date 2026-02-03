<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartamentoGeografico extends Model
{
    protected $table = 'departamentos_geograficos';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'departamento_geo_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
