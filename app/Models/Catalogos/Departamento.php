<?php

namespace App\Models\Catalogos;

use App\Models\Personal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $table = 'departamentos';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Personal que pertenece a este departamento
     */
    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class, 'departamento_id');
    }
}
