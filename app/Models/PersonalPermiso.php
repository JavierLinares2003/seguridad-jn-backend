<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $personal_id
 * @property string $tipo
 * @property float $cantidad_aprobada
 * @property \Carbon\Carbon|null $fecha_inicio
 * @property \Carbon\Carbon|null $fecha_fin
 * @property string $descripcion
 * @property string|null $observaciones
 * @property string $compensa_con
 * @property \Carbon\Carbon|null $fecha_recuperacion
 * @property int|null $vacacion_id
 * @property int|null $registrado_por_user_id
 * @property string|null $documento_ruta
 * @property string|null $documento_nombre_original
 * @property string|null $documento_extension
 * @property int|null $documento_tamanio_kb
 * @property-read float $horas_repuestas
 * @property-read float $saldo_pendiente
 * @property-read bool $tiene_documento
 * @property-read bool $recuperado
 */
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
        'compensa_con',
        'fecha_recuperacion',
        'vacacion_id',
        'registrado_por_user_id',
        'documento_ruta',
        'documento_nombre_original',
        'documento_extension',
        'documento_tamanio_kb',
    ];

    protected $casts = [
        'cantidad_aprobada'  => 'float',
        'fecha_inicio'       => 'date',
        'fecha_fin'          => 'date',
        'fecha_recuperacion' => 'date',
        'vacacion_id'        => 'integer',
    ];

    protected $appends = ['horas_repuestas', 'saldo_pendiente', 'tiene_documento', 'recuperado'];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function vacacion(): BelongsTo
    {
        return $this->belongsTo(PersonalVacacion::class, 'vacacion_id');
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
        if ($this->compensa_con === 'vacaciones' || $this->fecha_recuperacion) {
            return 0;
        }

        return max(0, $this->cantidad_aprobada - $this->horas_repuestas);
    }

    public function getRecuperadoAttribute(): bool
    {
        return $this->saldo_pendiente <= 0;
    }

    public function getTieneDocumentoAttribute(): bool
    {
        return ! empty($this->documento_ruta);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /** Permisos con saldo pendiente de reposición. */
    public function scopeConSaldoPendiente(Builder $query): Builder
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('compensa_con')->orWhere('compensa_con', '!=', 'vacaciones');
            })
            ->whereNull('fecha_recuperacion')
            ->whereRaw(
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
