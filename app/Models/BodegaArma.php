<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodegaArma extends Model
{
    use AuditableModel;

    protected string $logName = 'bodega_armas';
    protected string $modulo = 'bodega';

    public const TIPOS = [
        'revolver' => 'Revólver',
        '9mm' => '9mm',
        'escopeta' => 'Escopeta',
    ];

    public const ESTADOS = [
        'en_bodega' => 'En bodega',
        'asignada' => 'Asignada',
        'mantenimiento' => 'Mantenimiento',
        'baja' => 'Baja',
    ];

    protected $table = 'bodega_armas';

    protected $fillable = [
        'codigo',
        'codigo_interno',
        'tipo',
        'marca',
        'modelo',
        'serie',
        'tenencia',
        'portacion',
        'vencimiento',
        'responsable_nombre',
        'personal_id',
        'proyecto_id',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'vencimiento' => 'date',
    ];

    protected $appends = ['tipo_label', 'estado_label', 'alerta_vencimiento'];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getAlertaVencimientoAttribute(): ?string
    {
        if (!$this->vencimiento) {
            return null;
        }

        $fecha = $this->vencimiento->copy()->startOfDay();
        $hoy = Carbon::today();

        if ($fecha->lt($hoy)) {
            return 'vencida';
        }

        if ($fecha->lte($hoy->copy()->addDays(30))) {
            return 'por_vencer';
        }

        return 'vigente';
    }
}
