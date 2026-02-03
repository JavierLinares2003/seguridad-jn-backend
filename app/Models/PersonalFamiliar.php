<?php

namespace App\Models;

use App\Models\Catalogos\Parentesco;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalFamiliar extends Model
{
    protected $table = 'personal_familiares';

    protected $fillable = [
        'personal_id',
        'parentesco_id',
        'nombre_completo',
        'telefono',
        'es_contacto_emergencia',
    ];

    protected function casts(): array
    {
        return [
            'es_contacto_emergencia' => 'boolean',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function parentesco(): BelongsTo
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }
}
