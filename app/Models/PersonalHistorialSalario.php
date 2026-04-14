<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalHistorialSalario extends Model
{
    protected $table = 'personal_historial_salarios';

    protected $fillable = [
        'personal_id',
        'salario_anterior',
        'salario_nuevo',
        'fecha_cambio',
        'cambiado_por',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'salario_anterior' => 'decimal:2',
            'salario_nuevo'    => 'decimal:2',
            'fecha_cambio'     => 'date',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function cambiadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cambiado_por');
    }
}
