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
            // Primero descansos y presentes (sin cubridor) para que el trigger
            // vea el descanso del cubridor antes de validar el reemplazo.
            $ordenadas = collect($asistencias)->sortBy(function ($datos) {
                if (!empty($datos['personal_reemplazo_id'])) {
                    return 2;
                }
                return !empty($datos['es_descanso']) ? 0 : 1;
            })->values();

            foreach ($ordenadas as $index => $datos) {
                try {
                    $datos = $this->normalizarDatosAsistencia($datos);

                    if (!empty($datos['personal_asignado_id'])) {
                        $asistencia = OperacionAsistencia::crearOActualizar(
                            $datos['personal_asignado_id'],
                            $datos['fecha_asistencia'],
                            $datos,
                            $userId
                        );
                        $asistencia->load(['asignacion.personal', 'asignacion.proyecto']);
                    } else {
                        $asistencia = OperacionAsistencia::crearOActualizarDirecta(
                            $datos['personal_id'],
                            $datos['fecha_asistencia'],
                            $datos,
                            $userId
                        );
                        $asistencia->load(['personal']);
                    }

                    $this->sincronizarFilaCobertura($asistencia);
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
     * Cubridores: sin puesto, o con puesto pero de DESCANSO ese día.
     * No incluye a quien trabaja (presente/extra) ni al titular.
     */
    public function getPersonalDisponibleParaReemplazo(Carbon $fecha, ?int $proyectoId = null, ?int $excluirPersonalId = null): Collection
    {
        $asignacionesVigentes = OperacionPersonalAsignado::query()
            ->with(['proyecto:id,nombre_proyecto', 'configuracionPuesto'])
            ->where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->get()
            ->keyBy('personal_id');

        $asistenciasAsignadas = OperacionAsistencia::query()
            ->whereDate('fecha_asistencia', $fecha)
            ->whereIn('personal_asignado_id', $asignacionesVigentes->pluck('id'))
            ->get()
            ->keyBy('personal_asignado_id');

        $yaCubren = OperacionAsistencia::query()
            ->whereDate('fecha_asistencia', $fecha)
            ->where(function ($q) {
                $q->where('es_cobertura', true)
                    ->orWhereNotNull('personal_id');
            })
            ->whereNull('personal_asignado_id')
            ->pluck('personal_id')
            ->filter()
            ->all();

        $candidatos = Personal::query()
            ->operativo()
            ->whereIn('estado', ['activo', 'extrero'])
            ->when($excluirPersonalId, fn ($q) => $q->where('id', '!=', $excluirPersonalId))
            ->with(['sexo', 'departamento'])
            ->orderBy('nombres')
            ->get();

        return $candidatos->map(function (Personal $persona) use ($asignacionesVigentes, $asistenciasAsignadas, $yaCubren, $proyectoId) {
            if (in_array($persona->id, $yaCubren, true)) {
                return null;
            }

            $asignacion = $asignacionesVigentes->get($persona->id);

            if (!$asignacion) {
                $persona->origen_cobertura = 'disponible';
                $persona->proyecto_origen = null;
                $persona->puesto_origen = null;
                return $persona;
            }

            $asistencia = $asistenciasAsignadas->get($asignacion->id);
            if (!$asistencia || !$asistencia->es_descanso) {
                return null;
            }

            if ($proyectoId && (int) $asignacion->proyecto_id === (int) $proyectoId) {
                return null;
            }

            $persona->origen_cobertura = 'descanso';
            $persona->proyecto_origen = $asignacion->proyecto?->nombre_proyecto;
            $persona->puesto_origen = $asignacion->configuracionPuesto?->nombre_puesto;
            return $persona;
        })->filter()->values();
    }

    /**
     * Verifica si un personal puede ser cubridor ese día.
     */
    public function puedeSerReemplazo(int $personalId, Carbon $fecha): array
    {
        $personal = Personal::find($personalId);

        if (!$personal) {
            return ['puede' => false, 'razon' => 'Personal no encontrado.'];
        }

        if (!in_array($personal->estado, ['activo', 'extrero'], true)) {
            return ['puede' => false, 'razon' => 'El personal no está activo.'];
        }

        if ($personal->es_administrativo) {
            return ['puede' => false, 'razon' => 'El personal administrativo no cubre asistencia de campo.'];
        }

        $asignacion = OperacionPersonalAsignado::query()
            ->where('personal_id', $personalId)
            ->where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->first();

        if (!$asignacion) {
            return ['puede' => true, 'razon' => null, 'origen' => 'disponible'];
        }

        $descanso = OperacionAsistencia::query()
            ->where('personal_asignado_id', $asignacion->id)
            ->whereDate('fecha_asistencia', $fecha)
            ->where('es_descanso', true)
            ->exists();

        if (!$descanso) {
            return ['puede' => false, 'razon' => 'El personal está de turno en su puesto. Solo puede cubrir si está de descanso.'];
        }

        return ['puede' => true, 'razon' => null, 'origen' => 'descanso'];
    }

    /**
     * Obtiene historial de asistencia de un empleado.
     */
    public function getHistorialPersonal(int $personalId, Carbon $fechaInicio, Carbon $fechaFin): array
    {
        $asistencias = OperacionAsistencia::porPersonal($personalId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.proyecto', 'asignacion.turno', 'personal', 'proyectoCobertura'])
            ->orderBy('fecha_asistencia', 'desc')
            ->get();

        $totalDias = $fechaInicio->diffInDays($fechaFin) + 1;
        $propias = $asistencias->where('es_cobertura', false);
        $diasTrabajados = $propias->where('es_descanso', false)->where('es_ausente', false)->count()
            + $asistencias->where('es_cobertura', true)->count();
        $diasDescanso = $propias->where('es_descanso', true)->count();
        $diasAusente = $propias->where('es_ausente', true)->count();

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
                'dias_cobertura' => $asistencias->where('es_cobertura', true)->count(),
                'porcentaje_asistencia' => ($totalDias - $diasDescanso) > 0
                    ? round(($diasTrabajados / ($totalDias - $diasDescanso)) * 100, 2)
                    : 0,
            ],
            'registros' => $asistencias->map(fn($a) => [
                'fecha' => $a->fecha_asistencia->toDateString(),
                'proyecto' => $a->asignacion?->proyecto?->nombre_proyecto
                    ?? $a->proyectoCobertura?->nombre_proyecto
                    ?? ($a->esAsistenciaDirecta() ? ($a->es_cobertura ? 'Cobertura' : 'Sin asignación') : null),
                'turno' => $a->asignacion?->turno?->nombre,
                'estado' => $a->estado_dia,
                'es_extra' => (bool) $a->es_extra,
                'es_cobertura' => (bool) $a->es_cobertura,
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

        $asistenciasPorDia = OperacionAsistencia::porPersonal($personalId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.proyecto', 'asignacion.turno', 'proyectoCobertura'])
            ->get()
            ->groupBy(fn (OperacionAsistencia $a) => $a->fecha_asistencia->format('Y-m-d'));

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
            $delDia = collect($asistenciasPorDia->get($fecha, []));
            $cobertura = $delDia->firstWhere('es_cobertura', true);
            $asistencia = $delDia->first(fn (OperacionAsistencia $a) => !$a->es_cobertura)
                ?? $cobertura;
            $reemplazo = $reemplazos->get($fecha);

            $diaBase = [
                'fecha' => $fecha,
                'dia_semana' => $cursor->copy()->locale('es')->dayName,
                'cubrio' => null,
            ];

            if ($asistencia) {
                $estado = $asistencia->estado_dia;
                $tipo = match ($estado) {
                    'cobertura' => 'cobertura',
                    'descanso' => 'descanso',
                    'reemplazado' => 'reemplazado',
                    'ausente_justificado', 'ausente_injustificado', 'ausente_con_permiso' => 'falta',
                    'extra' => 'extra',
                    default => 'trabajo',
                };

                $cubrioInfo = $this->infoCobertura($cobertura, $reemplazo);
                if ($cubrioInfo && $tipo === 'descanso') {
                    $tipo = 'cobertura';
                }

                $calendario->push([
                    ...$diaBase,
                    'es_trabajo' => in_array($tipo, ['trabajo', 'reemplazado', 'extra', 'cobertura'], true),
                    'tipo' => $tipo,
                    'estado_asistencia' => $estado,
                    'registrado' => true,
                    'origen' => $asistencia->es_cobertura
                        ? 'cobertura'
                        : ($asistencia->esAsistenciaDirecta() ? 'sin_asignacion' : 'asistencia'),
                    'es_ausente' => (bool) $asistencia->es_ausente,
                    'es_descanso' => (bool) $asistencia->es_descanso,
                    'es_extra' => (bool) $asistencia->es_extra,
                    'es_cobertura' => (bool) ($asistencia->es_cobertura || $cubrioInfo),
                    'observaciones' => $cubrioInfo['observaciones'] ?? $asistencia->observaciones,
                    'proyecto' => $cubrioInfo['proyecto']
                        ?? $asistencia->asignacion?->proyecto?->nombre_proyecto
                        ?? $asistencia->proyectoCobertura?->nombre_proyecto,
                    'cubrio' => $cubrioInfo,
                ]);
            } elseif ($reemplazo) {
                $cubrioInfo = $this->infoCobertura(null, $reemplazo);
                $calendario->push([
                    ...$diaBase,
                    'es_trabajo' => true,
                    'tipo' => 'cobertura',
                    'estado_asistencia' => 'cobertura',
                    'registrado' => true,
                    'origen' => 'reemplazo',
                    'es_ausente' => false,
                    'es_descanso' => false,
                    'es_extra' => true,
                    'es_cobertura' => true,
                    'observaciones' => $cubrioInfo['observaciones'] ?? null,
                    'proyecto' => $cubrioInfo['proyecto'] ?? null,
                    'cubrio' => $cubrioInfo,
                ]);
            } else {
                $asignacionDia = $this->asignacionQueCubre($asignacionesPeriodo, $fecha, 0);
                $tipoVacio = $personal->es_administrativo
                    ? 'sin_marcar'
                    : ($asignacionDia ? 'sin_marcar' : 'sin_asignacion');

                $calendario->push([
                    ...$diaBase,
                    'es_trabajo' => false,
                    'tipo' => $tipoVacio,
                    'estado_asistencia' => null,
                    'registrado' => false,
                    'origen' => $asignacionDia ? 'puesto_anterior' : 'pendiente',
                    'puesto_anterior' => (bool) $asignacionDia,
                    'es_ausente' => false,
                    'es_descanso' => false,
                    'es_extra' => false,
                    'es_cobertura' => false,
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
                'dias_trabajados' => $calendario->whereIn('tipo', ['trabajo', 'extra', 'cobertura'])->count(),
                'dias_extra' => $calendario->where('tipo', 'extra')->count(),
                'dias_cobertura' => $calendario->where('tipo', 'cobertura')->count(),
                'dias_descanso' => $calendario->filter(fn ($d) => !empty($d['es_descanso']) || $d['tipo'] === 'descanso')->count(),
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
            $cobertura = $delDia->firstWhere('es_cobertura', true);
            $asistencia = $delDia->firstWhere('personal_asignado_id', $asignacionActualId)
                ?? $delDia->first(fn (OperacionAsistencia $a) => !$a->es_cobertura)
                ?? $cobertura;

            if ($asistencia) {
                $base = $this->diaDesdeAsistencia($dia, $asistencia, $asignacionActualId);
                $reemplazo = $reemplazos->get($fecha);
                $info = $this->infoCobertura($cobertura, $reemplazo);
                if ($info) {
                    $base['tipo'] = $asistencia->es_descanso ? 'cobertura' : $base['tipo'];
                    $base['es_cobertura'] = true;
                    $base['es_trabajo'] = true;
                    $base['observaciones'] = $info['observaciones'];
                    $base['cubrio'] = $info;
                    if ($info['proyecto']) {
                        $base['proyecto'] = $info['proyecto'];
                    }
                }
                return $base;
            }

            $reemplazo = $reemplazos->get($fecha);
            if ($reemplazo) {
                $cubierto = $reemplazo->asignacion?->personal?->nombre_completo ?? 'otro agente';

                return [
                    ...$dia,
                    'es_trabajo' => true,
                    'tipo' => 'cobertura',
                    'estado_asistencia' => 'cobertura',
                    'registrado' => true,
                    'origen' => 'reemplazo',
                    'puesto_anterior' => (int) $reemplazo->personal_asignado_id !== $asignacionActualId,
                    'es_ausente' => false,
                    'es_descanso' => false,
                    'es_extra' => true,
                    'es_cobertura' => true,
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
                    'cobertura' => 'cobertura',
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
            'es_trabajo' => in_array($tipo, ['trabajo', 'reemplazado', 'extra', 'cobertura'], true),
                'tipo' => $tipo,
                'estado_asistencia' => $estado,
                'registrado' => true,
                'origen' => $esOtra ? 'puesto_anterior' : ($asistencia->esAsistenciaDirecta() ? 'sin_asignacion' : 'asistencia'),
                'puesto_anterior' => $esOtra,
                'es_ausente' => (bool) $asistencia->es_ausente,
                'es_descanso' => (bool) $asistencia->es_descanso,
                'es_extra' => (bool) $asistencia->es_extra,
                'es_cobertura' => (bool) $asistencia->es_cobertura,
                'observaciones' => $asistencia->observaciones,
                'proyecto' => $asistencia->asignacion?->proyecto?->nombre_proyecto
                    ?? $asistencia->proyectoCobertura?->nombre_proyecto,
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
            if (empty($datos['personal_reemplazo_id'])) {
                $datos['fue_reemplazado'] = false;
                $datos['personal_reemplazo_id'] = null;
                $datos['motivo_reemplazo'] = null;
            } else {
                $datos['fue_reemplazado'] = true;
                $datos['motivo_reemplazo'] = $datos['motivo_reemplazo'] ?: 'Cobertura';
            }

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
            if (!empty($datos['personal_reemplazo_id'])) {
                $datos['fue_reemplazado'] = true;
                $datos['motivo_reemplazo'] = $datos['motivo_reemplazo'] ?: 'Cobertura';
            }

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
            return 'El cubridor está de turno en su puesto. Solo puede cubrir si está de descanso o sin puesto.';
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

    /**
     * Crea o quita la fila pagable del cubridor, sin tocar su descanso en el puesto titular.
     */
    private function sincronizarFilaCobertura(OperacionAsistencia $titular): void
    {
        if ($titular->es_cobertura) {
            return;
        }

        $fecha = $titular->fecha_asistencia;

        OperacionAsistencia::query()
            ->where('es_cobertura', true)
            ->where('asistencia_titular_id', $titular->id)
            ->when($titular->personal_reemplazo_id, function ($q) use ($titular) {
                $q->where('personal_id', '!=', $titular->personal_reemplazo_id);
            })
            ->get()
            ->each(fn (OperacionAsistencia $row) => $row->delete());

        if (!$titular->personal_reemplazo_id || (!$titular->es_descanso && !$titular->es_ausente)) {
            OperacionAsistencia::query()
                ->where('es_cobertura', true)
                ->where('asistencia_titular_id', $titular->id)
                ->get()
                ->each(fn (OperacionAsistencia $row) => $row->delete());
            return;
        }

        $puede = $this->puedeSerReemplazo(
            (int) $titular->personal_reemplazo_id,
            Carbon::parse($fecha)
        );
        if (empty($puede['puede'])) {
            throw new \RuntimeException($puede['razon'] ?? 'El personal no puede cubrir este día.');
        }

        $titular->loadMissing(['asignacion.proyecto', 'asignacion.personal']);
        $proyectoId = $titular->asignacion?->proyecto_id;
        $nombreTitular = $titular->asignacion?->personal?->nombre_completo ?? 'titular';
        $proyectoNombre = $titular->asignacion?->proyecto?->nombre_proyecto ?? 'otro proyecto';

        $existente = OperacionAsistencia::query()
            ->where('personal_id', $titular->personal_reemplazo_id)
            ->whereNull('personal_asignado_id')
            ->whereDate('fecha_asistencia', $fecha)
            ->first();

        if ($existente && !$existente->es_cobertura) {
            throw new \RuntimeException('El cubridor ya tiene asistencia sin puesto ese día.');
        }
        if ($existente && $existente->es_cobertura && (int) $existente->asistencia_titular_id !== (int) $titular->id) {
            throw new \RuntimeException('El cubridor ya cubrió otro puesto este día.');
        }

        OperacionAsistencia::crearOActualizarDirecta(
            (int) $titular->personal_reemplazo_id,
            $fecha,
            [
                'es_cobertura' => true,
                'es_extra' => true,
                'es_descanso' => false,
                'es_ausente' => false,
                'fue_reemplazado' => false,
                'personal_reemplazo_id' => null,
                'asistencia_titular_id' => $titular->id,
                'proyecto_cobertura_id' => $proyectoId,
                'observaciones' => "Cubrió a {$nombreTitular} en {$proyectoNombre}",
            ],
            $titular->registrado_por_user_id
        );
    }

    public function coberturasDelDia(Carbon $fecha, array $personalIds): Collection
    {
        if ($personalIds === []) {
            return collect();
        }

        return OperacionAsistencia::query()
            ->where('es_cobertura', true)
            ->whereDate('fecha_asistencia', $fecha)
            ->whereIn('personal_id', $personalIds)
            ->with(['proyectoCobertura:id,nombre_proyecto', 'asistenciaTitular.asignacion.personal:id,nombres,apellidos'])
            ->get()
            ->keyBy('personal_id');
    }

    private function infoCobertura(?OperacionAsistencia $cobertura, ?OperacionAsistencia $reemplazoFilaTitular): ?array
    {
        $fila = $cobertura ?: $reemplazoFilaTitular;
        if (!$fila) {
            return null;
        }

        $cubierto = $fila->asignacion?->personal?->nombre_completo
            ?? $reemplazoFilaTitular?->asignacion?->personal?->nombre_completo
            ?? 'otro agente';
        $proyecto = $fila->proyectoCobertura?->nombre_proyecto
            ?? $fila->asignacion?->proyecto?->nombre_proyecto
            ?? $reemplazoFilaTitular?->asignacion?->proyecto?->nombre_proyecto;

        return [
            'proyecto' => $proyecto,
            'titular' => $cubierto,
            'observaciones' => "Cubrió a {$cubierto}" . ($proyecto ? " en {$proyecto}" : ''),
        ];
    }
}
