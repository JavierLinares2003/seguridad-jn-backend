<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectoRequerimientoDevolucion extends Model
{
    protected $table = 'proyecto_requerimiento_devoluciones';

    protected $fillable = [
        'proyecto_id',
        'fecha',
        'nombres',
        'observaciones',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProyectoRequerimientoDevolucionItem::class, 'devolucion_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
