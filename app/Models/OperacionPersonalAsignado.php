<?php

namespace App\Models;

use App\Models\Catalogos\Turno;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class OperacionPersonalAsignado extends Model
{
    use HasFactory;

    protected $table = 'operaciones_personal_asignado';

    protected $fillable = [
        'personal_id',
        'proyecto_id',
        'configuracion_puesto_id',
        'turno_id',
        'fecha_inicio',
        'fecha_fin',
        'estado_asignacion',
        'motivo_suspension',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function configuracionPuesto(): BelongsTo
    {
        return $this->belongsTo(ProyectoConfiguracionPersonal::class, 'configuracion_puesto_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(OperacionAsistencia::class, 'personal_asignado_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado_asignacion', 'activa');
    }

    public function scopeFinalizadas(Builder $query): Builder
    {
        return $query->where('estado_asignacion', 'finalizada');
    }

    public function scopeSuspendidas(Builder $query): Builder
    {
        return $query->where('estado_asignacion', 'suspendida');
    }

    public function scopeVigentes(Builder $query, ?Carbon $fecha = null): Builder
    {
        $fecha = $fecha ?? Carbon::today();

        return $query->where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            });
    }

    public function scopeEnRangoFechas(Builder $query, Carbon $fechaInicio, ?Carbon $fechaFin = null): Builder
    {
        return $query->where(function ($q) use ($fechaInicio, $fechaFin) {
            $q->where(function ($subQ) use ($fechaInicio, $fechaFin) {
                // Asignaciones sin fecha fin
                $subQ->whereNull('fecha_fin')
                     ->where(function ($inner) use ($fechaInicio, $fechaFin) {
                         if ($fechaFin) {
                             $inner->where('fecha_inicio', '<=', $fechaFin);
                         } else {
                             $inner->whereRaw('1=1');
                         }
                     });
            })->orWhere(function ($subQ) use ($fechaInicio, $fechaFin) {
                // Asignaciones con fecha fin
                $subQ->whereNotNull('fecha_fin')
                     ->where('fecha_fin', '>=', $fechaInicio);

                if ($fechaFin) {
                    $subQ->where('fecha_inicio', '<=', $fechaFin);
                }
            });
        });
    }

    public function scopeByProyecto(Builder $query, int $proyectoId): Builder
    {
        return $query->where('proyecto_id', $proyectoId);
    }

    public function scopeByPersonal(Builder $query, int $personalId): Builder
    {
        return $query->where('personal_id', $personalId);
    }

    public function scopeByPuesto(Builder $query, int $configuracionPuestoId): Builder
    {
        return $query->where('configuracion_puesto_id', $configuracionPuestoId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function estaVigente(?Carbon $fecha = null): bool
    {
        $fecha = $fecha ?? Carbon::today();

        if ($this->estado_asignacion !== 'activa') {
            return false;
        }

        if ($this->fecha_inicio > $fecha) {
            return false;
        }

        if ($this->fecha_fin && $this->fecha_fin < $fecha) {
            return false;
        }

        return true;
    }

    public function tieneConflicto(Carbon $fechaInicio, ?Carbon $fechaFin = null): bool
    {
        if ($this->estado_asignacion !== 'activa') {
            return false;
        }

        // Si la asignación actual no tiene fecha fin
        if (is_null($this->fecha_fin)) {
            if (is_null($fechaFin)) {
                return true; // Ambas indefinidas, hay conflicto
            }
            return $fechaFin >= $this->fecha_inicio;
        }

        // Si la asignación actual tiene fecha fin
        if (is_null($fechaFin)) {
            return $fechaInicio <= $this->fecha_fin;
        }

        // Ambas tienen fecha fin
        return $fechaInicio <= $this->fecha_fin && $fechaFin >= $this->fecha_inicio;
    }

    public function finalizar(?string $motivo = null): bool
    {
        $this->estado_asignacion = 'finalizada';
        $this->fecha_fin = $this->fecha_fin ?? Carbon::today();

        if ($motivo) {
            $this->notas = $this->notas
                ? $this->notas . "\n[Finalizada]: " . $motivo
                : "[Finalizada]: " . $motivo;
        }

        return $this->save();
    }

    public function suspender(string $motivo): bool
    {
        $this->estado_asignacion = 'suspendida';
        $this->motivo_suspension = $motivo;

        return $this->save();
    }

    public function reactivar(): bool
    {
        $this->estado_asignacion = 'activa';
        $this->motivo_suspension = null;

        return $this->save();
    }

    public function getDiasAsignado(): int
    {
        $fechaFin = $this->fecha_fin ?? Carbon::today();
        return $this->fecha_inicio->diffInDays($fechaFin) + 1;
    }

    public function getCostoEstimado(): float
    {
        if (!$this->configuracionPuesto) {
            return 0;
        }

        $dias = $this->getDiasAsignado();
        $horasPorDia = $this->turno?->horas_trabajo ?? 8;
        $pagoHora = $this->configuracionPuesto->pago_hora_personal;

        return $dias * $horasPorDia * $pagoHora;
    }

    public function getIngresoEstimado(): float
    {
        if (!$this->configuracionPuesto) {
            return 0;
        }

        $dias = $this->getDiasAsignado();
        $horasPorDia = $this->turno?->horas_trabajo ?? 8;
        $costoHora = $this->configuracionPuesto->costo_hora_proyecto;

        return $dias * $horasPorDia * $costoHora;
    }
}
