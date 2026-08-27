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
     * Registra asistencia para múltiples asignaciones o personal directo.
     *
     * Soporta:
     * - personal_asignado_id: asistencia vinculada a una asignación
     * - personal_id: asistencia directa a personal sin asignación
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
                    // Normalizar datos para evitar inconsistencias
                    $datos = $this->normalizarDatosAsistencia($datos);

                    // Determinar si es asistencia con asignación o directa
                    if (!empty($datos['personal_asignado_id'])) {
                        // Asistencia con asignación (comportamiento original)
                        $asistencia = OperacionAsistencia::crearOActualizar(
                            $datos['personal_asignado_id'],
                            $datos['fecha_asistencia'],
                            $datos,
                            $userId
                        );
                        $asistencia->load(['asignacion.personal', 'asignacion.proyecto']);
                    } else {
                        // Asistencia directa a personal sin asignación
                        $asistencia = OperacionAsistencia::crearOActualizarDirecta(
                            $datos['personal_id'],
                            $datos['fecha_asistencia'],
                            $datos,
                            $userId
                        );
                        $asistencia->load(['personal']);
                    }

                    $resultados['exitosos'][] = $asistencia;
                } catch (\Exception $e) {
                    $resultados['errores'][] = [
                        'index' => $index,
                        'personal_asignado_id' => $datos['personal_asignado_id'] ?? null,
                        'personal_id' => $datos['personal_id'] ?? null,
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
    public function getAsistenciaPorProyectoYFecha(?int $proyectoId, Carbon $fecha, ?string $buscar = null): Collection
    {
        // Obtener todas las asignaciones activas del proyecto para esa fecha
        $asignaciones = OperacionPersonalAsignado::with(['personal', 'turno', 'configuracionPuesto.tipoPersonal'])
            ->vigentes($fecha)
            ->when($buscar, fn ($q) => $q->whereHas('personal', fn ($pq) => $pq->buscar($buscar)));

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
                    'estado' => $asistencia->estado_dia,
                    'es_descanso' => $asistencia->es_descanso,
                    'es_extra' => $asistencia->es_extra,
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
                'presentes' => $asistencias->where('es_descanso', false)->where('es_ausente', false)->count(),
                'extras' => $asistencias->where('es_extra', true)->count(),
                'ausentes' => $asistencias->where('es_ausente', true)->count(),
                'descansos' => $asistencias->where('es_descanso', true)->count(),
                'reemplazos' => $asistencias->where('fue_reemplazado', true)->count(),
            ],
            'por_fecha' => $asistencias->groupBy(fn($a) => $a->fecha_asistencia->toDateString())
                ->map(fn($grupo) => [
                    'presentes' => $grupo->where('es_descanso', false)->where('es_ausente', false)->count(),
                    'ausentes' => $grupo->where('es_ausente', true)->count(),
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
            ->whereIn('estado', ['activo', 'extrero'])
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
            ->with(['asignacion.proyecto', 'asignacion.turno', 'personal'])
            ->orderBy('fecha_asistencia', 'desc')
            ->get();

        $totalDias = $fechaInicio->diffInDays($fechaFin) + 1;
        $diasTrabajados = $asistencias->where('es_descanso', false)->where('es_ausente', false)->count();
        $diasDescanso = $asistencias->where('es_descanso', true)->count();
        $diasAusente = $asistencias->where('es_ausente', true)->count();

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
                'dias_ausente' => $diasAusente,
                'dias_extra' => $asistencias->where('es_extra', true)->count(),
                'porcentaje_asistencia' => ($totalDias - $diasDescanso) > 0
                    ? round(($diasTrabajados / ($totalDias - $diasDescanso)) * 100, 2)
                    : 0,
            ],
            'registros' => $asistencias->map(fn($a) => [
                'fecha' => $a->fecha_asistencia->toDateString(),
                'proyecto' => $a->asignacion?->proyecto?->nombre_proyecto
                    ?? ($a->esAsistenciaDirecta() ? 'Sin asignación' : null),
                'turno' => $a->asignacion?->turno?->nombre,
                'estado' => $a->estado_dia,
                'es_extra' => (bool) $a->es_extra,
                'asignacion_id' => $a->personal_asignado_id,
                'observaciones' => $a->observaciones,
            ]),
        ];
    }

    /**
     * Calendario día a día de asistencia de un agente (con o sin puesto asignado).
     */
    public function getCalendarioDiasTrabajados(int $personalId, Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $personal = Personal::findOrFail($personalId);

        $asistencias = OperacionAsistencia::porPersonal($personalId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.proyecto', 'asignacion.turno'])
            ->get()
            ->keyBy(fn (OperacionAsistencia $a) => $a->fecha_asistencia->format('Y-m-d'));

        $reemplazos = OperacionAsistencia::query()
            ->where('personal_reemplazo_id', $personalId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.personal', 'asignacion.proyecto'])
            ->get()
            ->keyBy(fn (OperacionAsistencia $a) => $a->fecha_asistencia->format('Y-m-d'));

        $asignacionesPeriodo = OperacionPersonalAsignado::with('proyecto')
            ->where('personal_id', $personalId)
            ->whereDate('fecha_inicio', '<=', $fechaFin->toDateString())
            ->where(function ($q) use ($fechaInicio) {
                $q->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fechaInicio->toDateString());
            })
            ->orderBy('fecha_inicio')
            ->get();

        $calendario = collect();
        $cursor = $fechaInicio->copy()->startOfDay();
        $fin = $fechaFin->copy()->startOfDay();

        while ($cursor->lte($fin)) {
            $fecha = $cursor->format('Y-m-d');
            $asistencia = $asistencias->get($fecha);
            $reemplazo = $reemplazos->get($fecha);

            if ($asistencia) {
                $estado = $asistencia->estado_dia;
                $tipo = match ($estado) {
                    'descanso' => 'descanso',
                    'reemplazado' => 'reemplazado',
                    'ausente_justificado', 'ausente_injustificado', 'ausente_con_permiso' => 'falta',
                    'extra' => 'extra',
                    default => 'trabajo',
                };

                $calendario->push([
                    'fecha' => $fecha,
                    'dia_semana' => $cursor->copy()->locale('es')->dayName,
                    'es_trabajo' => in_array($tipo, ['trabajo', 'reemplazado', 'extra'], true),
                    'tipo' => $tipo,
                    'estado_asistencia' => $estado,
                    'registrado' => true,
                    'origen' => $asistencia->esAsistenciaDirecta() ? 'sin_asignacion' : 'asistencia',
                    'es_ausente' => (bool) $asistencia->es_ausente,
                    'es_descanso' => (bool) $asistencia->es_descanso,
                    'es_extra' => (bool) $asistencia->es_extra,
                    'observaciones' => $asistencia->observaciones,
                    'proyecto' => $asistencia->asignacion?->proyecto?->nombre_proyecto,
                ]);
            } elseif ($reemplazo) {
                $cubierto = $reemplazo->asignacion?->personal?->nombre_completo
                    ?? 'otro agente';

                $calendario->push([
                    'fecha' => $fecha,
                    'dia_semana' => $cursor->copy()->locale('es')->dayName,
                    'es_trabajo' => true,
                    'tipo' => 'trabajo',
                    'estado_asistencia' => 'presente',
                    'registrado' => true,
                    'origen' => 'reemplazo',
                    'es_ausente' => false,
                    'es_descanso' => false,
                    'es_extra' => false,
                    'observaciones' => $reemplazo->motivo_reemplazo
                        ? "Cubrió a {$cubierto}. {$reemplazo->motivo_reemplazo}"
                        : "Cubrió a {$cubierto}",
                    'proyecto' => $reemplazo->asignacion?->proyecto?->nombre_proyecto,
                ]);
            } else {
                $asignacionDia = $this->asignacionQueCubre($asignacionesPeriodo, $fecha, 0);

                $calendario->push([
                    'fecha' => $fecha,
                    'dia_semana' => $cursor->copy()->locale('es')->dayName,
                    'es_trabajo' => false,
                    'tipo' => $asignacionDia ? 'sin_marcar' : 'sin_asignacion',
                    'estado_asistencia' => null,
                    'registrado' => false,
                    'origen' => $asignacionDia ? 'puesto_anterior' : 'pendiente',
                    'puesto_anterior' => (bool) $asignacionDia,
                    'es_ausente' => false,
                    'es_descanso' => false,
                    'es_extra' => false,
                    'observaciones' => null,
                    'proyecto' => $asignacionDia?->proyecto?->nombre_proyecto,
                ]);
            }

            $cursor->addDay();
        }

        return [
            'personal' => [
                'id' => $personal->id,
                'nombre' => $personal->nombre_completo,
            ],
            'proyecto' => null,
            'turno' => null,
            'fecha_inicio_asignacion' => null,
            'sin_asignacion' => true,
            'resumen' => [
                'dias_trabajados' => $calendario->whereIn('tipo', ['trabajo', 'extra'])->count(),
                'dias_extra' => $calendario->where('tipo', 'extra')->count(),
                'dias_descanso' => $calendario->where('tipo', 'descanso')->count(),
                'dias_falta' => $calendario->where('tipo', 'falta')->count(),
                'dias_sin_marcar' => $calendario->where('tipo', 'sin_marcar')->count(),
            ],
            'calendario' => $calendario->values(),
        ];
    }

    /**
     * Sobre el calendario del puesto actual, incorpora asistencia y
     * asignaciones de otros puestos del mismo agente (cambio de titular).
     */
    public function incorporarHistorialPuestos(
        Collection $calendario,
        int $personalId,
        int $asignacionActualId,
        Carbon $fechaInicio,
        Carbon $fechaFin
    ): array {
        $asistencias = OperacionAsistencia::porPersonal($personalId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.proyecto'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (OperacionAsistencia $a) => $a->fecha_asistencia->format('Y-m-d'));

        $reemplazos = OperacionAsistencia::query()
            ->where('personal_reemplazo_id', $personalId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.personal', 'asignacion.proyecto'])
            ->get()
            ->keyBy(fn (OperacionAsistencia $a) => $a->fecha_asistencia->format('Y-m-d'));

        $asignaciones = OperacionPersonalAsignado::with(['proyecto', 'turno'])
            ->where('personal_id', $personalId)
            ->whereDate('fecha_inicio', '<=', $fechaFin->toDateString())
            ->where(function ($q) use ($fechaInicio) {
                $q->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fechaInicio->toDateString());
            })
            ->orderBy('fecha_inicio')
            ->get();

        $puestosAnteriores = $asignaciones
            ->filter(fn (OperacionPersonalAsignado $a) => $a->id !== $asignacionActualId)
            ->map(fn (OperacionPersonalAsignado $a) => [
                'id' => $a->id,
                'proyecto' => $a->proyecto?->nombre_proyecto,
                'fecha_inicio' => $a->fecha_inicio?->toDateString(),
                'fecha_fin' => $a->fecha_fin?->toDateString(),
                'estado' => $a->estado_asignacion,
            ])
            ->values();

        $calendario = $calendario->map(function (array $dia) use ($asistencias, $reemplazos, $asignaciones, $asignacionActualId) {
            $fecha = $dia['fecha'];
            $delDia = collect($asistencias->get($fecha, []));
            $asistencia = $delDia->firstWhere('personal_asignado_id', $asignacionActualId)
                ?? $delDia->first();

            if ($asistencia) {
                return $this->diaDesdeAsistencia($dia, $asistencia, $asignacionActualId);
            }

            $reemplazo = $reemplazos->get($fecha);
            if ($reemplazo) {
                $cubierto = $reemplazo->asignacion?->personal?->nombre_completo ?? 'otro agente';

                return [
                    ...$dia,
                    'es_trabajo' => true,
                    'tipo' => 'trabajo',
                    'estado_asistencia' => 'presente',
                    'registrado' => true,
                    'origen' => 'reemplazo',
                    'puesto_anterior' => (int) $reemplazo->personal_asignado_id !== $asignacionActualId,
                    'es_ausente' => false,
                    'es_descanso' => false,
                    'es_extra' => false,
                    'observaciones' => $reemplazo->motivo_reemplazo
                        ? "Cubrió a {$cubierto}. {$reemplazo->motivo_reemplazo}"
                        : "Cubrió a {$cubierto}",
                    'proyecto' => $reemplazo->asignacion?->proyecto?->nombre_proyecto,
                ];
            }

            $otra = $this->asignacionQueCubre($asignaciones, $fecha, $asignacionActualId);
            if ($otra) {
                return [
                    ...$dia,
                    'tipo' => 'sin_marcar',
                    'es_trabajo' => false,
                    'registrado' => false,
                    'origen' => 'puesto_anterior',
                    'puesto_anterior' => true,
                    'proyecto' => $otra->proyecto?->nombre_proyecto,
                ];
            }

            return $dia;
        });

        return [
            'calendario' => $calendario->values(),
            'puestos_anteriores' => $puestosAnteriores,
        ];
    }

    private function diaDesdeAsistencia(array $dia, OperacionAsistencia $asistencia, int $asignacionActualId): array
    {
        $estado = $asistencia->estado_dia;
        $tipo = match ($estado) {
            'descanso' => 'descanso',
            'reemplazado' => 'reemplazado',
            'ausente_justificado', 'ausente_injustificado', 'ausente_con_permiso' => 'falta',
            'extra' => 'extra',
            default => 'trabajo',
        };

        $esOtra = $asistencia->personal_asignado_id
            && (int) $asistencia->personal_asignado_id !== $asignacionActualId;

        return [
            ...$dia,
            'es_trabajo' => in_array($tipo, ['trabajo', 'reemplazado', 'extra'], true),
            'tipo' => $tipo,
            'estado_asistencia' => $estado,
            'registrado' => true,
            'origen' => $esOtra ? 'puesto_anterior' : ($asistencia->esAsistenciaDirecta() ? 'sin_asignacion' : 'asistencia'),
            'puesto_anterior' => $esOtra,
            'es_ausente' => (bool) $asistencia->es_ausente,
            'es_descanso' => (bool) $asistencia->es_descanso,
            'es_extra' => (bool) $asistencia->es_extra,
            'observaciones' => $asistencia->observaciones,
            'proyecto' => $asistencia->asignacion?->proyecto?->nombre_proyecto,
        ];
    }

    private function asignacionQueCubre(Collection $asignaciones, string $fecha, int $excluirId): ?OperacionPersonalAsignado
    {
        $fechaCarbon = Carbon::parse($fecha)->startOfDay();

        return $asignaciones->first(function (OperacionPersonalAsignado $a) use ($fechaCarbon, $excluirId) {
            if ($a->id === $excluirId) {
                return false;
            }

            $inicio = Carbon::parse($a->fecha_inicio)->startOfDay();
            $fin = $a->fecha_fin ? Carbon::parse($a->fecha_fin)->startOfDay() : null;

            return $fechaCarbon->gte($inicio) && (!$fin || $fechaCarbon->lte($fin));
        });
    }

    /**
     * Normaliza los datos de asistencia para mantener coherencia.
     *
     * Prioridad:
     * 1. Si es_descanso = true → limpia todo
     * 2. Si es_ausente = true → limpia hora_entrada/salida
     * 3. Si hora_entrada existe → limpia campos de ausencia/reemplazo
     */
    private function normalizarDatosAsistencia(array $datos): array
    {
        // Si es descanso, limpiar todo lo demás
        if (!empty($datos['es_descanso']) && $datos['es_descanso'] === true) {
            $datos['hora_entrada'] = null;
            $datos['hora_salida'] = null;
            $datos['llego_tarde'] = false;
            $datos['minutos_retraso'] = 0;
            $datos['es_ausente'] = false;
            $datos['es_extra'] = false;
            $datos['motivo_ausencia_id'] = null;
            $datos['descripcion_ausencia'] = null;
            $datos['tipo_ausencia'] = null;
            $datos['tipo_inasistencia'] = null;
            $datos['permiso_ausencia_id'] = null;
            $datos['fue_reemplazado'] = false;
            $datos['personal_reemplazo_id'] = null;
            $datos['motivo_reemplazo'] = null;

            return $datos;
        }

        // Si es ausente, limpiar solo campos de asistencia normal
        if (!empty($datos['es_ausente']) && $datos['es_ausente'] === true) {
            $datos['es_descanso'] = false;
            $datos['es_extra'] = false;
            $datos['hora_entrada'] = null;
            $datos['hora_salida'] = null;
            $datos['llego_tarde'] = false;
            $datos['minutos_retraso'] = 0;
            // No limpiar permiso_reposicion_id ni horas_reposicion (se preservan si vienen)

            return $datos;
        }

        // Asistencia normal (presente / extra): limpiar campos de ausencia pero preservar reposición
        $datos['es_ausente'] = false;
        $datos['motivo_ausencia_id'] = null;
        $datos['descripcion_ausencia'] = null;
        $datos['tipo_ausencia'] = null;
        $datos['tipo_inasistencia'] = null;
        $datos['permiso_ausencia_id'] = null;
        $datos['es_descanso'] = false;
        $datos['es_extra'] = !empty($datos['es_extra']);
        // permiso_reposicion_id y horas_reposicion se preservan si vienen en datos

        return $datos;
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
        if (str_contains($mensaje, 'P0017')) {
            return 'El personal no existe o está eliminado.';
        }
        if (str_contains($mensaje, 'P0018')) {
            return 'El personal no está activo.';
        }
        if (str_contains($mensaje, 'asistencia_unica_dia')) {
            return 'Ya existe un registro de asistencia para esta fecha.';
        }

        return 'Error al procesar la asistencia.';
    }
}
