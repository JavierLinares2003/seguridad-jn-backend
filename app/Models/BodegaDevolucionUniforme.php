<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodegaDevolucionUniforme extends Model
{
    protected $table = 'bodega_devoluciones_uniforme';

    protected $fillable = [
        'personal_id',
        'nombre_persona',
        'fecha',
        'prendas',
        'observaciones',
        'registrado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'prendas' => 'array',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }
}
