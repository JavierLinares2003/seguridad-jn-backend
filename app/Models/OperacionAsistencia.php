<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class OperacionAsistencia extends Model
{
    use HasFactory, AuditableModel;

    protected string $logName = 'asistencia';
    protected string $modulo = 'operaciones';

    protected $table = 'operaciones_asistencia';

    protected $fillable = [
        'personal_asignado_id',
        'fecha_asistencia',
        'hora_entrada',
        'hora_salida',
        'llego_tarde',
        'minutos_retraso',
        'es_descanso',
        'fue_reemplazado',
        'personal_reemplazo_id',
        'motivo_reemplazo',
        'observaciones',
        'registrado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_asistencia' => 'date',
            'hora_entrada' => 'datetime:H:i',
            'hora_salida' => 'datetime:H:i',
            'llego_tarde' => 'boolean',
            'minutos_retraso' => 'integer',
            'es_descanso' => 'boolean',
            'fue_reemplazado' => 'boolean',
        ];
    }

    protected $appends = ['horas_trabajadas', 'estado_dia'];

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    protected function horasTrabajadas(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->es_descanso || !$this->hora_entrada || !$this->hora_salida) {
                    return 0;
                }

                $entrada = Carbon::parse($this->hora_entrada);
                $salida = Carbon::parse($this->hora_salida);

                // Si la salida es antes que la entrada, asumimos que cruzó medianoche
                if ($salida < $entrada) {
                    $salida->addDay();
                }

                return round($entrada->diffInMinutes($salida) / 60, 2);
            }
        );
    }

    protected function estadoDia(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->es_descanso) {
                    return 'descanso';
                }

                if ($this->fue_reemplazado) {
                    return 'reemplazado';
                }

                if (!$this->hora_entrada) {
                    return 'ausente';
                }

                if ($this->llego_tarde) {
                    return 'tarde';
                }

                return 'presente';
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function asignacion(): BelongsTo
    {
        return $this->belongsTo(OperacionPersonalAsignado::class, 'personal_asignado_id');
    }

    public function personalReemplazo(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_reemplazo_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePorFecha(Builder $query, Carbon|string $fecha): Builder
    {
        return $query->where('fecha_asistencia', $fecha);
    }

    public function scopePorRangoFechas(Builder $query, Carbon|string $fechaInicio, Carbon|string $fechaFin): Builder
    {
        return $query->whereBetween('fecha_asistencia', [$fechaInicio, $fechaFin]);
    }

    public function scopePorProyecto(Builder $query, int $proyectoId): Builder
    {
        return $query->whereHas('asignacion', function ($q) use ($proyectoId) {
            $q->where('proyecto_id', $proyectoId);
        });
    }

    public function scopePorPersonal(Builder $query, int $personalId): Builder
    {
        return $query->whereHas('asignacion', function ($q) use ($personalId) {
            $q->where('personal_id', $personalId);
        });
    }

    public function scopeDescansos(Builder $query): Builder
    {
        return $query->where('es_descanso', true);
    }

    public function scopeAsistencias(Builder $query): Builder
    {
        return $query->where('es_descanso', false);
    }

    public function scopeConRetraso(Builder $query): Builder
    {
        return $query->where('llego_tarde', true);
    }

    public function scopeReemplazados(Builder $query): Builder
    {
        return $query->where('fue_reemplazado', true);
    }

    public function scopeAusentes(Builder $query): Builder
    {
        return $query->where('es_descanso', false)
                     ->where('fue_reemplazado', false)
                     ->whereNull('hora_entrada');
    }

    public function scopePresentes(Builder $query): Builder
    {
        return $query->where('es_descanso', false)
                     ->whereNotNull('hora_entrada');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function marcarEntrada(string $hora, ?int $userId = null): bool
    {
        $this->hora_entrada = $hora;
        $this->registrado_por_user_id = $userId ?? $this->registrado_por_user_id;

        return $this->save();
    }

    public function marcarSalida(string $hora, ?int $userId = null): bool
    {
        $this->hora_salida = $hora;
        $this->registrado_por_user_id = $userId ?? $this->registrado_por_user_id;

        return $this->save();
    }

    public function asignarReemplazo(int $personalReemplazoId, string $motivo, ?int $userId = null): bool
    {
        $this->fue_reemplazado = true;
        $this->personal_reemplazo_id = $personalReemplazoId;
        $this->motivo_reemplazo = $motivo;
        $this->registrado_por_user_id = $userId ?? $this->registrado_por_user_id;

        return $this->save();
    }

    public function quitarReemplazo(): bool
    {
        $this->fue_reemplazado = false;
        $this->personal_reemplazo_id = null;
        $this->motivo_reemplazo = null;

        return $this->save();
    }

    public function esDescanso(): bool
    {
        return $this->es_descanso;
    }

    public function estaPresente(): bool
    {
        return !$this->es_descanso && $this->hora_entrada !== null;
    }

    public function estaAusente(): bool
    {
        return !$this->es_descanso && !$this->fue_reemplazado && $this->hora_entrada === null;
    }

    public function fueReemplazado(): bool
    {
        return $this->fue_reemplazado;
    }

    public function llegoTarde(): bool
    {
        return $this->llego_tarde;
    }

    public function getResumen(): array
    {
        return [
            'fecha' => $this->fecha_asistencia->toDateString(),
            'estado' => $this->estado_dia,
            'hora_entrada' => $this->hora_entrada?->format('H:i'),
            'hora_salida' => $this->hora_salida?->format('H:i'),
            'horas_trabajadas' => $this->horas_trabajadas,
            'minutos_retraso' => $this->minutos_retraso,
            'reemplazo' => $this->fue_reemplazado ? [
                'personal_id' => $this->personal_reemplazo_id,
                'motivo' => $this->motivo_reemplazo,
            ] : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Static Methods
    |--------------------------------------------------------------------------
    */

    public static function crearOActualizar(
        int $personalAsignadoId,
        Carbon|string $fecha,
        array $datos,
        ?int $userId = null
    ): self {
        return self::updateOrCreate(
            [
                'personal_asignado_id' => $personalAsignadoId,
                'fecha_asistencia' => $fecha,
            ],
            array_merge($datos, [
                'registrado_por_user_id' => $userId,
            ])
        );
    }
}
