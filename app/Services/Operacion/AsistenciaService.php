<?php

namespace App\Services\Operacion;

use App\Models\OperacionAsistencia;
use App\Models\OperacionPersonalAsignado;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AsistenciaService
{
    /**
     * Registra asistencia para múltiples asignaciones.
     */
    public function registrarAsistenciaMasiva(array $asistencias, ?int $userId = null): array
    {
        $resultados = [
            'exitosos' => [],
            'errores' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($asistencias as $index => $datos) {
                try {
                    $asistencia = OperacionAsistencia::crearOActualizar(
                        $datos['personal_asignado_id'],
                        $datos['fecha_asistencia'],
                        $datos,
                        $userId
                    );

                    $asistencia->load(['asignacion.personal', 'asignacion.proyecto']);
                    $resultados['exitosos'][] = $asistencia;
                } catch (\Exception $e) {
                    $resultados['errores'][] = [
                        'index' => $index,
                        'personal_asignado_id' => $datos['personal_asignado_id'] ?? null,
                        'fecha' => $datos['fecha_asistencia'] ?? null,
                        'error' => $this->parsearErrorPostgres($e->getMessage()),
                    ];
                }
            }

            // Si hay errores, hacer rollback
            if (count($resultados['errores']) > 0) {
                DB::rollBack();
                return $resultados;
            }

            DB::commit();
            return $resultados;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene la asistencia de un proyecto en una fecha.
     */
    public function getAsistenciaPorProyectoYFecha(?int $proyectoId, Carbon $fecha): Collection
    {
        // Obtener todas las asignaciones activas del proyecto para esa fecha
        $asignaciones = OperacionPersonalAsignado::with(['personal', 'turno', 'configuracionPuesto.tipoPersonal'])
            ->where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            });

        if ($proyectoId > 0) {
            $asignaciones->where('proyecto_id', $proyectoId);
        } else {
            // Unassigned (NULL por ahora)
            // Si el frontend envia 0 o NULL para "Sin Asignacion"
            $asignaciones->whereNull('proyecto_id');
        }

        $asignaciones = $asignaciones->get();

        // Obtener asistencias registradas
        $asistencias = OperacionAsistencia::with(['personalReemplazo', 'registradoPor'])
            ->whereIn('personal_asignado_id', $asignaciones->pluck('id'))
            ->where('fecha_asistencia', $fecha)
            ->get()
            ->keyBy('personal_asignado_id');

        // Combinar datos
        return $asignaciones->map(function ($asignacion) use ($asistencias, $fecha) {
            $asistencia = $asistencias->get($asignacion->id);

            return [
                'asignacion' => [
                    'id' => $asignacion->id,
                    'personal' => $asignacion->personal,
                    'turno' => $asignacion->turno,
                    'puesto' => $asignacion->configuracionPuesto?->nombre_puesto,
                    'tipo_personal' => $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                ],
                'asistencia' => $asistencia ? [
                    'id' => $asistencia->id,
                    'hora_entrada' => $asistencia->hora_entrada?->format('H:i'),
                    'hora_salida' => $asistencia->hora_salida?->format('H:i'),
                    'estado' => $asistencia->estado_dia,
                    'llego_tarde' => $asistencia->llego_tarde,
                    'minutos_retraso' => $asistencia->minutos_retraso,
                    'es_descanso' => $asistencia->es_descanso,
                    'fue_reemplazado' => $asistencia->fue_reemplazado,
                    'reemplazo' => $asistencia->personalReemplazo,
                    'motivo_reemplazo' => $asistencia->motivo_reemplazo,
                    'observaciones' => $asistencia->observaciones,
                ] : [
                    'id' => null,
                    'estado' => 'sin_registro',
                ],
                'fecha' => $fecha->toDateString(),
            ];
        });
    }

    /**
     * Obtiene resumen de asistencia de un proyecto en un rango.
     */
    public function getResumenAsistencia(int $proyectoId, Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $asistencias = OperacionAsistencia::porProyecto($proyectoId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->get();

        return [
            'periodo' => [
                'inicio' => $fechaInicio->toDateString(),
                'fin' => $fechaFin->toDateString(),
                'dias' => $fechaInicio->diffInDays($fechaFin) + 1,
            ],
            'estadisticas' => [
                'total_registros' => $asistencias->count(),
                'presentes' => $asistencias->where('es_descanso', false)->whereNotNull('hora_entrada')->count(),
                'ausentes' => $asistencias->where('es_descanso', false)->where('fue_reemplazado', false)->whereNull('hora_entrada')->count(),
                'descansos' => $asistencias->where('es_descanso', true)->count(),
                'reemplazos' => $asistencias->where('fue_reemplazado', true)->count(),
                'tardanzas' => $asistencias->where('llego_tarde', true)->count(),
                'total_minutos_retraso' => $asistencias->sum('minutos_retraso'),
            ],
            'por_fecha' => $asistencias->groupBy(fn($a) => $a->fecha_asistencia->toDateString())
                ->map(fn($grupo) => [
                    'presentes' => $grupo->where('es_descanso', false)->whereNotNull('hora_entrada')->count(),
                    'ausentes' => $grupo->where('es_descanso', false)->where('fue_reemplazado', false)->whereNull('hora_entrada')->count(),
                    'descansos' => $grupo->where('es_descanso', true)->count(),
                    'reemplazos' => $grupo->where('fue_reemplazado', true)->count(),
                ]),
        ];
    }

    /**
     * Genera descansos automáticos para turnos que lo requieren.
     */
    public function generarDescansosAutomaticos(Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $resultado = DB::select(
            'SELECT * FROM generar_descansos_automaticos(?, ?)',
            [$fechaInicio->toDateString(), $fechaFin->toDateString()]
        );

        return [
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'descansos_generados' => count($resultado),
            'detalle' => collect($resultado)->map(fn($r) => [
                'asignacion_id' => $r->asignacion_id,
                'fecha' => $r->fecha_descanso,
            ])->toArray(),
        ];
    }

    /**
     * Obtiene personal disponible para reemplazo en una fecha.
     */
    public function getPersonalDisponibleParaReemplazo(Carbon $fecha, ?int $proyectoId = null): Collection
    {
        // Personal activo sin asignación ese día
        $personalConAsignacion = OperacionPersonalAsignado::query()
            ->where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->pluck('personal_id');

        return Personal::query()
            ->activos()
            ->whereNotIn('id', $personalConAsignacion)
            ->with(['sexo', 'departamento'])
            ->get();
    }

    /**
     * Verifica si un personal puede ser reemplazo.
     */
    public function puedeSerReemplazo(int $personalId, Carbon $fecha): array
    {
        $personal = Personal::find($personalId);

        if (!$personal) {
            return ['puede' => false, 'razon' => 'Personal no encontrado.'];
        }

        if ($personal->estado !== 'activo') {
            return ['puede' => false, 'razon' => 'El personal no está activo.'];
        }

        // Verificar si tiene asignación activa ese día
        $tieneAsignacion = OperacionPersonalAsignado::query()
            ->where('personal_id', $personalId)
            ->where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->exists();

        if ($tieneAsignacion) {
            return ['puede' => false, 'razon' => 'El personal tiene asignación activa en esa fecha.'];
        }

        return ['puede' => true, 'razon' => null];
    }

    /**
     * Obtiene historial de asistencia de un empleado.
     */
    public function getHistorialPersonal(int $personalId, Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $asistencias = OperacionAsistencia::porPersonal($personalId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.proyecto', 'asignacion.turno'])
            ->orderBy('fecha_asistencia', 'desc')
            ->get();

        $totalDias = $fechaInicio->diffInDays($fechaFin) + 1;
        $diasTrabajados = $asistencias->where('es_descanso', false)->whereNotNull('hora_entrada')->count();
        $diasDescanso = $asistencias->where('es_descanso', true)->count();
        $tardanzas = $asistencias->where('llego_tarde', true)->count();

        return [
            'personal_id' => $personalId,
            'periodo' => [
                'inicio' => $fechaInicio->toDateString(),
                'fin' => $fechaFin->toDateString(),
            ],
            'resumen' => [
                'total_dias' => $totalDias,
                'dias_trabajados' => $diasTrabajados,
                'dias_descanso' => $diasDescanso,
                'dias_ausente' => $totalDias - $diasTrabajados - $diasDescanso,
                'tardanzas' => $tardanzas,
                'total_minutos_retraso' => $asistencias->sum('minutos_retraso'),
                'horas_trabajadas' => $asistencias->sum('horas_trabajadas'),
                'porcentaje_asistencia' => $totalDias > 0
                    ? round(($diasTrabajados / ($totalDias - $diasDescanso)) * 100, 2)
                    : 0,
            ],
            'registros' => $asistencias->map(fn($a) => [
                'fecha' => $a->fecha_asistencia->toDateString(),
                'proyecto' => $a->asignacion?->proyecto?->nombre_proyecto,
                'turno' => $a->asignacion?->turno?->nombre,
                'estado' => $a->estado_dia,
                'hora_entrada' => $a->hora_entrada?->format('H:i'),
                'hora_salida' => $a->hora_salida?->format('H:i'),
                'horas_trabajadas' => $a->horas_trabajadas,
                'minutos_retraso' => $a->minutos_retraso,
                'observaciones' => $a->observaciones,
            ]),
        ];
    }

    /**
     * Parsea errores de PostgreSQL.
     */
    private function parsearErrorPostgres(string $mensaje): string
    {
        if (preg_match('/ERROR:\s*(.+?)(?:\s*CONTEXT:|$)/s', $mensaje, $matches)) {
            return trim($matches[1]);
        }

        if (str_contains($mensaje, 'P0010')) {
            return 'El personal de reemplazo ya tiene asignación activa.';
        }
        if (str_contains($mensaje, 'P0011')) {
            return 'La asignación no existe.';
        }
        if (str_contains($mensaje, 'P0012')) {
            return 'La fecha es anterior al inicio de la asignación.';
        }
        if (str_contains($mensaje, 'P0013')) {
            return 'La fecha es posterior al fin de la asignación.';
        }
        if (str_contains($mensaje, 'P0014')) {
            return 'No puede registrar salida sin entrada.';
        }
        if (str_contains($mensaje, 'P0015')) {
            return 'Debe especificar el personal de reemplazo.';
        }
        if (str_contains($mensaje, 'P0016')) {
            return 'Debe especificar el motivo del reemplazo.';
        }
        if (str_contains($mensaje, 'asistencia_unica_dia')) {
            return 'Ya existe un registro de asistencia para esta fecha.';
        }

        return 'Error al procesar la asistencia.';
    }
}
