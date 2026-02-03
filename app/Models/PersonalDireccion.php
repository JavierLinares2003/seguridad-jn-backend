<?php

namespace App\Models;

use App\Models\Catalogos\DepartamentoGeografico;
use App\Models\Catalogos\Municipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalDireccion extends Model
{
    protected $table = 'personal_direcciones';

    protected $fillable = [
        'personal_id',
        'departamento_geo_id',
        'municipio_id',
        'zona',
        'direccion_completa',
        'es_direccion_actual',
    ];

    protected function casts(): array
    {
        return [
            'zona' => 'integer',
            'es_direccion_actual' => 'boolean',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function departamentoGeografico(): BelongsTo
    {
        return $this->belongsTo(DepartamentoGeografico::class, 'departamento_geo_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }
}
