<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Municipio extends Model
{
    protected $table = 'municipios';

    protected $fillable = [
        'departamento_geo_id',
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

    public function departamentoGeografico(): BelongsTo
    {
        return $this->belongsTo(DepartamentoGeografico::class, 'departamento_geo_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeByDepartamento($query, $departamentoId)
    {
        return $query->where('departamento_geo_id', $departamentoId);
    }
}
