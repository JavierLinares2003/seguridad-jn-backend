<?php

namespace App\Models\Catalogos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodicidadPago extends Model
{
    use HasFactory;

    protected $table = 'periodicidades_pago';

    protected $fillable = [
        'nombre',
        'dias',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'dias' => 'integer',
    ];
}
