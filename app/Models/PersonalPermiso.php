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
 * @property-read float $horas_reposicion_programadas
 * @property-read float $saldo_pendiente
 * @property-read bool $tiene_documento
 * @property-read bool $recuperado
 * @property-read bool $es_constancia
 * @property-read bool $es_vacaciones
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

    /** Fechas programadas / declaradas de reposición del permiso. */
    public function fechasReposicion(): HasMany
    {
        return $this->hasMany(PersonalPermisoFechaReposicion::class, 'personal_permiso_id')
            ->orderBy('fecha');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getHorasRepuestasAttribute(): float
    {
        return (float) $this->reposiciones()->sum('horas_reposicion');
    }

    public function getHorasReposicionProgramadasAttribute(): float
    {
        if ($this->relationLoaded('fechasReposicion')) {
            return (float) $this->fechasReposicion->sum('horas');
        }

        return (float) $this->fechasReposicion()->sum('horas');
    }

    public function getEsVacacionesAttribute(): bool
    {
        return $this->compensa_con === 'vacaciones';
    }

    public function getEsConstanciaAttribute(): bool
    {
        return $this->compensa_con === 'constancia';
    }

    public function getSaldoPendienteAttribute(): float
    {
        if ($this->compensa_con === 'vacaciones' || $this->compensa_con === 'constancia') {
            return 0;
        }

        if ($this->fecha_recuperacion) {
            return 0;
        }

        $cubierto = $this->horas_repuestas + $this->horas_reposicion_programadas;

        return max(0, $this->cantidad_aprobada - $cubierto);
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
                $q->whereNull('compensa_con')
                    ->orWhereNotIn('compensa_con', ['vacaciones', 'constancia']);
            })
            ->whereNull('fecha_recuperacion')
            ->whereRaw(
                '(SELECT COALESCE(SUM(horas_reposicion),0) FROM operaciones_asistencia WHERE permiso_reposicion_id = personal_permisos.id)
                 + (SELECT COALESCE(SUM(horas),0) FROM personal_permiso_fechas_reposicion WHERE personal_permiso_id = personal_permisos.id)
                 < personal_permisos.cantidad_aprobada'
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
