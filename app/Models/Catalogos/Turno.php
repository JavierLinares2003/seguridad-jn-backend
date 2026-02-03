<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turnos';

    protected $fillable = [
        'nombre',
        'hora_inicio',
        'hora_fin',
        'horas_trabajo',
        'descripcion',
        'requiere_descanso',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'hora_inicio' => 'datetime:H:i',
            'hora_fin' => 'datetime:H:i',
            'horas_trabajo' => 'decimal:2',
            'requiere_descanso' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
