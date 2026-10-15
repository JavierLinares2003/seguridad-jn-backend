<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoRequerimientoEquipo extends Model
{
    protected $table = 'proyecto_requerimientos_equipo';

    protected $fillable = [
        'proyecto_id',
        'clave',
        'cantidad',
        'requerido',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'requerido' => 'boolean',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
