<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalTalla extends Model
{
    protected $table = 'personal_tallas';

    protected $fillable = [
        'personal_id',
        'talla_camisa',
        'talla_pantalon',
        'talla_zapato',
        'talla_chaleco',
        'talla_gorra',
        'genero_preferido',
        'observaciones',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
