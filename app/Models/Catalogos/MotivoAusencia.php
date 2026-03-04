<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotivoAusencia extends Model
{
    protected $table = 'catalogo_motivos_ausencia';

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_justificada',
        'aplica_descuento',
        'requiere_documento',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_justificada' => 'boolean',
            'aplica_descuento' => 'boolean',
            'requiere_documento' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeJustificadas($query)
    {
        return $query->where('es_justificada', true);
    }

    public function scopeInjustificadas($query)
    {
        return $query->where('es_justificada', false);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(\App\Models\OperacionAsistencia::class, 'motivo_ausencia_id');
    }
}
