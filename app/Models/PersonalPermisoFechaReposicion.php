<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $personal_permiso_id
 * @property \Carbon\Carbon $fecha
 * @property float $horas
 */
class PersonalPermisoFechaReposicion extends Model
{
    protected $table = 'personal_permiso_fechas_reposicion';

    protected $fillable = [
        'personal_permiso_id',
        'fecha',
        'horas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'horas' => 'float',
    ];

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(PersonalPermiso::class, 'personal_permiso_id');
    }
}
