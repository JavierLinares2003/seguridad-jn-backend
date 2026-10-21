<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoRequerimientoDevolucionItem extends Model
{
    protected $table = 'proyecto_requerimiento_devolucion_items';

    protected $fillable = [
        'devolucion_id',
        'clave',
        'devuelto',
    ];

    protected $casts = [
        'devuelto' => 'boolean',
    ];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(ProyectoRequerimientoDevolucion::class, 'devolucion_id');
    }
}
