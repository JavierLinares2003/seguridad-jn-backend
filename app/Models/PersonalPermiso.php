<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalPermiso extends Model
{
    protected $table = 'personal_permisos';

    protected $fillable = [
        'personal_id',
        'tipo',
        'cantidad_aprobada',
        'fecha_inicio',
        'fecha_fin',
        'descripcion',
        'observaciones',
        'registrado_por_user_id',
        'documento_ruta',
        'documento_nombre_original',
        'documento_extension',
        'documento_tamanio_kb',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_aprobada' => 'float',
            'fecha_inicio'      => 'date',
            'fecha_fin'         => 'date',
        ];
    }

    protected $appends = ['horas_repuestas', 'saldo_pendiente', 'tiene_documento'];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    /** Asistencias donde este permiso excusó una ausencia. */
    public function ausenciasVinculadas(): HasMany
    {
        return $this->hasMany(OperacionAsistencia::class, 'permiso_ausencia_id');
    }

    /** Asistencias donde se registró reposición de este permiso. */
    public function reposiciones(): HasMany
    {
        return $this->hasMany(OperacionAsistencia::class, 'permiso_reposicion_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getHorasRepuestasAttribute(): float
    {
        return (float) $this->reposiciones()->sum('horas_reposicion');
    }

    public function getSaldoPendienteAttribute(): float
    {
        return max(0, $this->cantidad_aprobada - $this->horas_repuestas);
    }

    public function getTieneDocumentoAttribute(): bool
    {
        return ! empty($this->documento_ruta);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Permisos con saldo pendiente de reposición. */
    public function scopeConSaldoPendiente(Builder $query): Builder
    {
        return $query->whereRaw(
            '(SELECT COALESCE(SUM(horas_reposicion),0) FROM operaciones_asistencia WHERE permiso_reposicion_id = personal_permisos.id) < personal_permisos.cantidad_aprobada'
        );
    }

    /** Permisos vigentes en una fecha dada (sin fecha_fin o fecha_fin >= fecha). */
    public function scopeVigentesEn(Builder $query, string $fecha): Builder
    {
        return $query->where('fecha_inicio', '<=', $fecha)
                     ->where(function ($q) use ($fecha) {
                         $q->whereNull('fecha_fin')
                           ->orWhere('fecha_fin', '>=', $fecha);
                     });
    }
}
