<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalReferenciaLaboral extends Model
{
    protected $table = 'personal_referencias_laborales';

    protected $fillable = [
        'personal_id',
        'nombre_empresa',
        'puesto_ocupado',
        'telefono',
        'direccion',
        'fecha_inicio',
        'fecha_fin',
        'motivo_retiro',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
