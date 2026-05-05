<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalVacacion extends Model
{
    protected $table = 'personal_vacaciones';

    protected $fillable = [
        'personal_id',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'dias_solicitados',
        'dias_aprobados',
        'descripcion',
        'observaciones',
        'documento_ruta',
        'documento_nombre_original',
        'documento_extension',
        'documento_tamanio_kb',
        'registrado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'anio'             => 'integer',
            'fecha_inicio'     => 'date',
            'fecha_fin'        => 'date',
            'dias_solicitados' => 'integer',
            'dias_aprobados'   => 'integer',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'registrado_por_user_id');
    }

    public function getTieneDocumentoAttribute(): bool
    {
        return ! empty($this->documento_ruta);
    }
}
