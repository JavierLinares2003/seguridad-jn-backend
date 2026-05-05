<?php

namespace App\Models;

use App\Models\Catalogos\Departamento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacacionConfig extends Model
{
    protected $table = 'vacaciones_config';

    protected $fillable = [
        'departamento_id',
        'dias_por_anio',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'departamento_id' => 'integer',
            'dias_por_anio'   => 'integer',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /**
     * Retorna los días anuales para un departamento.
     * Si no tiene config específica, usa el valor default (null departamento_id).
     * Si tampoco hay default, retorna 8.
     */
    public static function diasParaDepartamento(?int $departamentoId): int
    {
        if ($departamentoId) {
            $config = static::where('departamento_id', $departamentoId)->first();
            if ($config) {
                return $config->dias_por_anio;
            }
        }

        $default = static::whereNull('departamento_id')->first();

        return $default?->dias_por_anio ?? 8;
    }
}
