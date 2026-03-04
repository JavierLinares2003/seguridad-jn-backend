<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\RegistrarAsistenciaRequest;
use App\Http\Requests\Operacion\UpdateAsistenciaRequest;
use App\Models\Catalogos\MotivoAusencia;
use App\Models\OperacionAsistencia;
use App\Models\Proyecto;
use App\Services\Operacion\AsistenciaService;
use App\Services\TurnoCalculadorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OperacionAsistenciaController extends Controller implements HasMiddleware
{
    public function __construct(
        private AsistenciaService $asistenciaService,
        private TurnoCalculadorService $turnoCalculadorService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-operaciones', only: [
                'index', 'show', 'porFecha', 'porProyecto', 'resumen', 'historialPersonal',
                'reemplazosDisponibles', 'vistaAgrupada', 'motivosAusencia', 'calendarioTurno'
            ]),
            new Middleware('permission:manage-asistencia', only: [
                'store', 'update', 'destroy', 'generarDescansos', 'marcarEntrada', 'marcarSalida', 'marcarAusencia'
            ]),
        ];
    }

    /**
     * GET /api/v1/operaciones/asistencia
     * Lista asistencias con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = OperacionAsistencia::with([
            'asignacion.personal',
            'asignacion.proyecto',
            'asignacion.turno',
            'personalReemplazo',
            'registradoPor',
            'motivoAusencia',
        ]);

        // Filtros
        if ($request->filled('proyecto_id')) {
            $query->porProyecto($request->input('proyecto_id'));
        }

        if ($request->filled('personal_id')) {
            $query->porPersonal($request->input('personal_id'));
        }

        if ($request->filled('fecha')) {
            $query->porFecha($request->input('fecha'));
        }

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->porRangoFechas($request->input('fecha_inicio'), $request->input('fecha_fin'));
        }

        if ($request->filled('solo_descansos') && $request->boolean('solo_descansos')) {
            $query->descansos();
        }

        if ($request->filled('solo_tardanzas') && $request->boolean('solo_tardanzas')) {
            $query->conRetraso();
        }

        if ($request->filled('solo_reemplazos') && $request->boolean('solo_reemplazos')) {
            $query->reemplazados();
        }

        if ($request->filled('solo_ausentes') && $request->boolean('solo_ausentes')) {
            $query->ausentes();
        }

        if ($request->filled('solo_ausentes_justificados') && $request->boolean('solo_ausentes_justificados')) {
            $query->ausentesJustificados();
        }

        if ($request->filled('solo_ausentes_injustificados') && $request->boolean('solo_ausentes_injustificados')) {
            $query->ausentesInjustificados();
        }

        if ($request->filled('solo_sin_registro') && $request->boolean('solo_sin_registro')) {
            $query->sinRegistro();
        }

        // Ordenamiento
        $query->orderBy($request->input('orden_campo', 'fecha_asistencia'), $request->input('orden_dir', 'desc'));

        // Paginación
        $perPage = min($request->input('per_page', 15), 100);
        $asistencias = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $asistencias,
        ]);
    }

    /**
     * POST /api/v1/operaciones/asistencia
     * Registra asistencia (una o varias)
     */
    public function store(RegistrarAsistenciaRequest $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $resultado = $this->asistenciaService->registrarAsistenciaMasiva(
            $request->input('asistencias'),
            $userId
        );

        if (count($resultado['errores']) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Algunos registros no pudieron ser procesados.',
                'exitosos' => count($resultado['exitosos']),
                'errores' => $resultado['errores'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'data' => $resultado['exitosos'],
        ], 201);
    }

    /**
     * GET /api/v1/operaciones/asistencia/{id}
     * Muestra una asistencia específica
     */
    public function show(int $id): JsonResponse
    {
        $asistencia = OperacionAsistencia::with([
            'asignacion.personal',
            'asignacion.proyecto',
            'asignacion.turno',
            'asignacion.configuracionPuesto',
            'personalReemplazo',
            'registradoPor',
            'motivoAusencia',
            'planilla',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $asistencia,
        ]);
    }

    /**
     * PUT /api/v1/operaciones/asistencia/{id}
     * Actualiza una asistencia
     * RESTRICCIÓN: Solo se puede editar la asistencia del día anterior (ayer)
     */
    public function update(UpdateAsistenciaRequest $request, int $id): JsonResponse
    {
        $asistencia = OperacionAsistencia::findOrFail($id);

        // Verificar restricción de fecha: solo se puede editar el día de ayer
        $ayer = Carbon::yesterday();
        $fechaAsistencia = Carbon::parse($asistencia->fecha_asistencia);

        // Permitir bypass si el usuario tiene permiso especial (admin)
        $user = $request->user();
        $puedeEditarCualquierFecha = $user && $user->hasRole('admin');

        if (!$puedeEditarCualquierFecha && !$fechaAsistencia->isSameDay($ayer)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede editar la asistencia del día anterior. Fecha permitida: ' . $ayer->toDateString(),
            ], 422);
        }

        // Verificar si ya fue procesado en planilla
        if ($asistencia->procesado_planilla) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar una asistencia que ya fue procesada en planilla.',
            ], 422);
        }

        try {
            $asistencia->update(array_merge(
                $request->validated(),
                ['registrado_por_user_id' => $request->user()?->id]
            ));

            $asistencia->refresh()->load([
                'asignacion.personal',
                'asignacion.proyecto',
                'personalReemplazo',
                'motivoAusencia',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Asistencia actualizada correctamente.',
                'data' => $asistencia,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->parsearErrorPostgres($e->getMessage()),
            ], 422);
        }
    }

    /**
     * DELETE /api/v1/operaciones/asistencia/{id}
     * Elimina una asistencia
     */
    public function destroy(int $id): JsonResponse
    {
        $asistencia = OperacionAsistencia::findOrFail($id);
        $asistencia->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro de asistencia eliminado.',
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/fecha/{fecha}
     * Obtiene asistencia de una fecha específica
     *
     * Modos de uso:
     * - Sin parámetros: Lista todos los proyectos con su personal (paginado por proyectos, 10 por página)
     * - proyecto_id=X: Filtra por proyecto específico
     * - personal_id=X o buscar=nombre: Busca personal específico
     * - sin_asignar=true: Personal sin proyecto asignado, agrupado por departamento
     */
    public function porFecha(Request $request, string $fecha): JsonResponse
    {
        $request->validate([
            'proyecto_id' => 'nullable|integer',
            'personal_id' => 'nullable|integer|exists:personal,id',
            'buscar' => 'nullable|string|max:100',
            'sin_asignar' => 'nullable|in:true,false,1,0',
            'departamento_id' => 'nullable|integer|exists:departamentos,id',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $fechaCarbon = Carbon::parse($fecha);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de fecha inválido.',
            ], 422);
        }

        // CASO 1: Personal sin proyecto asignado, agrupado por departamento
        if ($request->boolean('sin_asignar')) {
            return $this->getPersonalSinAsignar(
                $fechaCarbon,
                $request->input('per_page', 15),
                $request->input('departamento_id')
            );
        }

        // CASO 2: Búsqueda de personal específico
        if ($request->filled('personal_id') || $request->filled('buscar')) {
            return $this->buscarPersonalEnFecha($request, $fechaCarbon);
        }

        // CASO 3: Proyecto específico
        if ($request->filled('proyecto_id')) {
            $asistencia = $this->asistenciaService->getAsistenciaPorProyectoYFecha(
                (int) $request->input('proyecto_id'),
                $fechaCarbon
            );

            return response()->json([
                'success' => true,
                'data' => $asistencia,
                'meta' => [
                    'fecha' => $fechaCarbon->toDateString(),
                    'proyecto_id' => $request->input('proyecto_id'),
                    'total_personal' => $asistencia->count(),
                ],
            ]);
        }

        // CASO 4 (Default): Todos los proyectos con su personal, paginado por proyectos
        return $this->getProyectosConPersonal($fechaCarbon, $request->input('per_page', 10));
    }

    /**
     * Retorna proyectos con su personal asignado para una fecha, paginado por proyectos.
     */
    private function getProyectosConPersonal(Carbon $fecha, int $perPage): JsonResponse
    {
        // Obtener IDs de proyectos que tienen asignaciones activas en esta fecha
        $proyectosConAsignaciones = \App\Models\OperacionPersonalAsignado::where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->whereNotNull('proyecto_id')
            ->distinct()
            ->pluck('proyecto_id');

        // Paginar proyectos - solo aquellos donde la fecha está en el rango del proyecto
        // Incluir proyectos en planificación y activos (excluir suspendido y finalizado)
        $proyectosPaginados = Proyecto::whereIn('id', $proyectosConAsignaciones)
            ->whereIn('estado_proyecto', ['planificacion', 'activo'])
            // La fecha debe estar dentro del rango del proyecto
            ->where(function ($q) use ($fecha) {
                // CASO 1: Si tiene fechas reales, usar esas
                $q->where(function ($q2) use ($fecha) {
                    $q2->whereNotNull('fecha_inicio_real')
                       ->where('fecha_inicio_real', '<=', $fecha)
                       ->where(function ($q3) use ($fecha) {
                           $q3->whereNull('fecha_fin_real')
                              ->orWhere('fecha_fin_real', '>=', $fecha);
                       });
                })
                // CASO 2: Si no tiene fechas reales pero sí estimadas, usar estimadas
                ->orWhere(function ($q2) use ($fecha) {
                    $q2->whereNull('fecha_inicio_real')
                       ->whereNotNull('fecha_inicio_estimada')
                       ->where('fecha_inicio_estimada', '<=', $fecha)
                       ->where(function ($q3) use ($fecha) {
                           $q3->whereNull('fecha_fin_estimada')
                              ->orWhere('fecha_fin_estimada', '>=', $fecha);
                       });
                })
                // CASO 3: Si no tiene ninguna fecha, incluir siempre (proyecto sin restricción de fechas)
                ->orWhere(function ($q2) {
                    $q2->whereNull('fecha_inicio_real')
                       ->whereNull('fecha_inicio_estimada');
                });
            })
            ->orderBy('nombre_proyecto')
            ->paginate($perPage);

        // Para cada proyecto, obtener su personal con asistencia
        $proyectosConPersonal = $proyectosPaginados->getCollection()->map(function ($proyecto) use ($fecha) {
            $asignaciones = \App\Models\OperacionPersonalAsignado::with([
                'personal',
                'turno',
                'configuracionPuesto.tipoPersonal',
            ])
            ->where('proyecto_id', $proyecto->id)
            ->where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->get();

            // Obtener asistencias para estas asignaciones
            $asistencias = OperacionAsistencia::with(['personalReemplazo', 'motivoAusencia'])
                ->whereIn('personal_asignado_id', $asignaciones->pluck('id'))
                ->where('fecha_asistencia', $fecha)
                ->get()
                ->keyBy('personal_asignado_id');

            // Combinar asignaciones con asistencias
            $personal = $asignaciones->map(function ($asignacion) use ($asistencias) {
                $asistencia = $asistencias->get($asignacion->id);
                return [
                    'asignacion_id' => $asignacion->id,
                    'personal' => $asignacion->personal,
                    'turno' => $asignacion->turno,
                    'puesto' => $asignacion->configuracionPuesto?->nombre_puesto
                        ?? $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                    'tipo_personal' => $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                    'asistencia' => $asistencia ? [
                        'id' => $asistencia->id,
                        'hora_entrada' => $asistencia->hora_entrada?->format('H:i'),
                        'hora_salida' => $asistencia->hora_salida?->format('H:i'),
                        'estado' => $asistencia->estado_dia,
                        'llego_tarde' => $asistencia->llego_tarde,
                        'minutos_retraso' => $asistencia->minutos_retraso,
                        'es_descanso' => $asistencia->es_descanso,
                        'es_ausente' => $asistencia->es_ausente,
                        'motivo_ausencia' => $asistencia->motivoAusencia,
                        'descripcion_ausencia' => $asistencia->descripcion_ausencia,
                        'tipo_ausencia' => $asistencia->tipo_ausencia,
                        'fue_reemplazado' => $asistencia->fue_reemplazado,
                        'reemplazo' => $asistencia->personalReemplazo,
                        'observaciones' => $asistencia->observaciones,
                    ] : ['id' => null, 'estado' => 'sin_registro'],
                ];
            });

            // Calcular resumen del proyecto
            $resumen = [
                'total' => $personal->count(),
                'presentes' => $personal->where('asistencia.estado', 'presente')->count(),
                'tardanzas' => $personal->where('asistencia.estado', 'tarde')->count(),
                'ausentes_justificados' => $personal->where('asistencia.estado', 'ausente_justificado')->count(),
                'ausentes_injustificados' => $personal->where('asistencia.estado', 'ausente_injustificado')->count(),
                'descansos' => $personal->where('asistencia.estado', 'descanso')->count(),
                'sin_registro' => $personal->where('asistencia.estado', 'sin_registro')->count(),
            ];

            return [
                'proyecto' => [
                    'id' => $proyecto->id,
                    'nombre' => $proyecto->nombre_proyecto,
                    'correlativo' => $proyecto->correlativo,
                    'empresa_cliente' => $proyecto->empresa_cliente,
                ],
                'personal' => $personal->values(),
                'resumen' => $resumen,
            ];
        });

        // Reconstruir el paginador con los datos transformados
        $proyectosPaginados->setCollection($proyectosConPersonal);

        return response()->json([
            'success' => true,
            'data' => $proyectosPaginados,
            'meta' => [
                'fecha' => $fecha->toDateString(),
            ],
        ]);
    }

    /**
     * Busca personal específico por ID o nombre en una fecha.
     */
    private function buscarPersonalEnFecha(Request $request, Carbon $fecha): JsonResponse
    {
        $query = \App\Models\OperacionPersonalAsignado::with([
            'personal',
            'proyecto',
            'turno',
            'configuracionPuesto.tipoPersonal',
        ])
        ->where('estado_asignacion', 'activa')
        ->where('fecha_inicio', '<=', $fecha)
        ->where(function ($q) use ($fecha) {
            $q->whereNull('fecha_fin')
              ->orWhere('fecha_fin', '>=', $fecha);
        });

        // Filtrar por personal_id
        if ($request->filled('personal_id')) {
            $query->where('personal_id', $request->input('personal_id'));
        }

        // Filtrar por búsqueda de nombre
        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->whereHas('personal', function ($q) use ($buscar) {
                $q->where('nombres', 'ilike', "%{$buscar}%")
                  ->orWhere('apellidos', 'ilike', "%{$buscar}%")
                  ->orWhere('dpi', 'ilike', "%{$buscar}%");
            });
        }

        $asignaciones = $query->get();

        // Obtener asistencias
        $asistencias = OperacionAsistencia::with(['personalReemplazo', 'motivoAusencia'])
            ->whereIn('personal_asignado_id', $asignaciones->pluck('id'))
            ->where('fecha_asistencia', $fecha)
            ->get()
            ->keyBy('personal_asignado_id');

        $resultados = $asignaciones->map(function ($asignacion) use ($asistencias) {
            $asistencia = $asistencias->get($asignacion->id);
            return [
                'asignacion_id' => $asignacion->id,
                'personal' => $asignacion->personal,
                'proyecto' => $asignacion->proyecto,
                'turno' => $asignacion->turno,
                'puesto' => $asignacion->configuracionPuesto?->nombre_puesto
                    ?? $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                'asistencia' => $asistencia ? [
                    'id' => $asistencia->id,
                    'hora_entrada' => $asistencia->hora_entrada?->format('H:i'),
                    'hora_salida' => $asistencia->hora_salida?->format('H:i'),
                    'estado' => $asistencia->estado_dia,
                    'es_ausente' => $asistencia->es_ausente,
                    'motivo_ausencia' => $asistencia->motivoAusencia,
                ] : ['id' => null, 'estado' => 'sin_registro'],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $resultados->values(),
            'meta' => [
                'fecha' => $fecha->toDateString(),
                'total_encontrados' => $resultados->count(),
            ],
        ]);
    }

    /**
     * Retorna personal sin proyecto asignado, agrupado por departamento.
     * Incluye asistencia directa si existe.
     *
     * Modos:
     * - Sin departamento_id: Vista general de todos los departamentos con límite de personal por depto
     * - Con departamento_id: Vista detallada de un departamento con paginación completa
     */
    private function getPersonalSinAsignar(Carbon $fecha, int $perPage = 15, ?int $departamentoId = null): JsonResponse
    {
        // Personal activo que NO tiene asignación activa en esta fecha
        $personalConAsignacion = \App\Models\OperacionPersonalAsignado::where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fecha);
            })
            ->pluck('personal_id');

        // CASO 1: Vista detallada de un departamento específico con paginación
        if ($departamentoId !== null) {
            return $this->getPersonalSinAsignarPorDepartamento($fecha, $departamentoId, $perPage, $personalConAsignacion);
        }

        // CASO 2: Vista general de todos los departamentos
        return $this->getPersonalSinAsignarVistaGeneral($fecha, $personalConAsignacion);
    }

    /**
     * Vista detallada: Un departamento con paginación de su personal
     */
    private function getPersonalSinAsignarPorDepartamento(
        Carbon $fecha,
        int $departamentoId,
        int $perPage,
        $personalConAsignacion
    ): JsonResponse {
        $personalQuery = \App\Models\Personal::with(['departamento'])
            ->where('estado', 'activo')
            ->whereNotIn('id', $personalConAsignacion)
            ->where('departamento_id', $departamentoId);

        $totalRegistros = $personalQuery->count();
        $personalPaginado = $personalQuery->paginate($perPage);
        $personal = collect($personalPaginado->items());

        // Obtener asistencias
        $asistenciasDirectas = OperacionAsistencia::whereNull('personal_asignado_id')
            ->whereIn('personal_id', $personal->pluck('id'))
            ->where('fecha_asistencia', $fecha)
            ->with(['motivoAusencia'])
            ->get()
            ->keyBy('personal_id');

        // Obtener info del departamento
        $departamento = $personal->first()?->departamento;

        $personalData = $personal->map(function ($p) use ($asistenciasDirectas) {
            $asistencia = $asistenciasDirectas->get($p->id);
            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre_completo' => $p->nombre_completo,
                'dpi' => $p->dpi,
                'telefono' => $p->telefono,
                'asistencia' => $asistencia ? [
                    'id' => $asistencia->id,
                    'hora_entrada' => $asistencia->hora_entrada?->format('H:i'),
                    'hora_salida' => $asistencia->hora_salida?->format('H:i'),
                    'estado' => $asistencia->estado_dia,
                    'es_descanso' => $asistencia->es_descanso,
                    'es_ausente' => $asistencia->es_ausente,
                    'motivo_ausencia' => $asistencia->motivoAusencia,
                    'observaciones' => $asistencia->observaciones,
                ] : ['id' => null, 'estado' => 'sin_registro'],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'departamento' => [
                    'id' => $departamentoId,
                    'nombre' => $departamento?->nombre ?? 'Sin departamento',
                ],
                'personal' => $personalData,
            ],
            'meta' => [
                'fecha' => $fecha->toDateString(),
                'total_personal' => $totalRegistros,
                'con_asistencia' => $asistenciasDirectas->count(),
                'sin_registro' => $personal->count() - $asistenciasDirectas->count(),
            ],
            'pagination' => [
                'current_page' => $personalPaginado->currentPage(),
                'last_page' => $personalPaginado->lastPage(),
                'per_page' => $personalPaginado->perPage(),
                'total' => $personalPaginado->total(),
                'from' => $personalPaginado->firstItem(),
                'to' => $personalPaginado->lastItem(),
            ],
        ]);
    }

    /**
     * Vista general: Todos los departamentos con límite de personal por departamento
     */
    private function getPersonalSinAsignarVistaGeneral(
        Carbon $fecha,
        $personalConAsignacion
    ): JsonResponse {
        $limitePorDepartamento = 10;

        // Obtener todos los departamentos que tienen personal sin asignar
        $departamentos = \App\Models\Catalogos\Departamento::whereHas('personal', function ($q) use ($personalConAsignacion) {
            $q->where('estado', 'activo')
              ->whereNotIn('id', $personalConAsignacion);
        })->orderBy('nombre')->get();

        $resultado = [];

        foreach ($departamentos as $departamento) {
            // Obtener personal de este departamento (limitado)
            $personalQuery = \App\Models\Personal::with(['departamento'])
                ->where('estado', 'activo')
                ->whereNotIn('id', $personalConAsignacion)
                ->where('departamento_id', $departamento->id);

            $totalEnDepartamento = $personalQuery->count();
            $personal = $personalQuery->limit($limitePorDepartamento)->get();

            // Obtener asistencias para este personal
            $asistenciasDirectas = OperacionAsistencia::whereNull('personal_asignado_id')
                ->whereIn('personal_id', $personal->pluck('id'))
                ->where('fecha_asistencia', $fecha)
                ->with(['motivoAusencia'])
                ->get()
                ->keyBy('personal_id');

            $personalData = $personal->map(function ($p) use ($asistenciasDirectas) {
                $asistencia = $asistenciasDirectas->get($p->id);
                return [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre_completo' => $p->nombre_completo,
                    'dpi' => $p->dpi,
                    'telefono' => $p->telefono,
                    'asistencia' => $asistencia ? [
                        'id' => $asistencia->id,
                        'hora_entrada' => $asistencia->hora_entrada?->format('H:i'),
                        'hora_salida' => $asistencia->hora_salida?->format('H:i'),
                        'estado' => $asistencia->estado_dia,
                        'es_descanso' => $asistencia->es_descanso,
                        'es_ausente' => $asistencia->es_ausente,
                        'motivo_ausencia' => $asistencia->motivoAusencia,
                        'observaciones' => $asistencia->observaciones,
                    ] : ['id' => null, 'estado' => 'sin_registro'],
                ];
            })->values();

            $resultado[] = [
                'departamento' => [
                    'id' => $departamento->id,
                    'nombre' => $departamento->nombre,
                ],
                'personal' => $personalData,
                'total_en_departamento' => $totalEnDepartamento,
                'mostrando' => $personal->count(),
                'hay_mas' => $totalEnDepartamento > $limitePorDepartamento,
                'resumen' => [
                    'con_asistencia' => $asistenciasDirectas->count(),
                    'sin_registro' => $personal->count() - $asistenciasDirectas->count(),
                ],
            ];
        }

        // Calcular totales generales
        $totalGeneral = \App\Models\Personal::where('estado', 'activo')
            ->whereNotIn('id', $personalConAsignacion)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $resultado,
            'meta' => [
                'fecha' => $fecha->toDateString(),
                'total_sin_asignar' => $totalGeneral,
                'total_departamentos' => count($resultado),
                'limite_por_departamento' => $limitePorDepartamento,
                'nota' => 'Use departamento_id para ver todos los registros de un departamento específico con paginación',
            ],
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/departamentos-disponibles/{fecha}
     * Lista departamentos que tienen personal sin asignar en una fecha específica
     */
    public function departamentosDisponibles(Request $request, string $fecha): JsonResponse
    {
        try {
            $fechaCarbon = Carbon::parse($fecha);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de fecha inválido.',
            ], 422);
        }

        // Personal activo que NO tiene asignación activa en esta fecha
        $personalConAsignacion = \App\Models\OperacionPersonalAsignado::where('estado_asignacion', 'activa')
            ->where('fecha_inicio', '<=', $fechaCarbon)
            ->where(function ($q) use ($fechaCarbon) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', $fechaCarbon);
            })
            ->pluck('personal_id');

        // Obtener departamentos con conteo de personal sin asignar
        $departamentos = \App\Models\Catalogos\Departamento::whereHas('personal', function ($q) use ($personalConAsignacion) {
            $q->where('estado', 'activo')
              ->whereNotIn('id', $personalConAsignacion);
        })
        ->withCount(['personal' => function ($q) use ($personalConAsignacion) {
            $q->where('estado', 'activo')
              ->whereNotIn('id', $personalConAsignacion);
        }])
        ->orderBy('nombre')
        ->get()
        ->map(function ($departamento) {
            return [
                'id' => $departamento->id,
                'nombre' => $departamento->nombre,
                'total_personal' => $departamento->personal_count,
            ];
        });

        $totalGeneral = \App\Models\Personal::where('estado', 'activo')
            ->whereNotIn('id', $personalConAsignacion)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $departamentos,
            'meta' => [
                'fecha' => $fechaCarbon->toDateString(),
                'total_departamentos' => $departamentos->count(),
                'total_personal_sin_asignar' => $totalGeneral,
            ],
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/proyecto/{proyectoId}
     * Obtiene asistencia de un proyecto con resumen
     */
    public function porProyecto(Request $request, int $proyectoId): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        Proyecto::findOrFail($proyectoId);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));

        // Limitar a 31 días
        if ($fechaInicio->diffInDays($fechaFin) > 31) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo es de 31 días.',
            ], 422);
        }

        $asistencias = OperacionAsistencia::porProyecto($proyectoId)
            ->porRangoFechas($fechaInicio, $fechaFin)
            ->with(['asignacion.personal', 'personalReemplazo'])
            ->orderBy('fecha_asistencia', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $asistencias,
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/resumen/{proyectoId}
     * Obtiene resumen estadístico de asistencia
     */
    public function resumen(Request $request, int $proyectoId): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        Proyecto::findOrFail($proyectoId);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));

        $resumen = $this->asistenciaService->getResumenAsistencia($proyectoId, $fechaInicio, $fechaFin);

        return response()->json([
            'success' => true,
            'data' => $resumen,
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/historial/{personalId}
     * Obtiene historial de asistencia de un empleado
     */
    public function historialPersonal(Request $request, int $personalId): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));

        // Limitar a 90 días
        if ($fechaInicio->diffInDays($fechaFin) > 90) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo es de 90 días.',
            ], 422);
        }

        $historial = $this->asistenciaService->getHistorialPersonal($personalId, $fechaInicio, $fechaFin);

        return response()->json([
            'success' => true,
            'data' => $historial,
        ]);
    }

    /**
     * POST /api/v1/operaciones/asistencia/generar-descansos
     * Genera descansos automáticos para turnos que lo requieren
     */
    public function generarDescansos(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));

        // Limitar a 31 días
        if ($fechaInicio->diffInDays($fechaFin) > 31) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo es de 31 días.',
            ], 422);
        }

        $resultado = $this->asistenciaService->generarDescansosAutomaticos($fechaInicio, $fechaFin);

        return response()->json([
            'success' => true,
            'message' => "Se generaron {$resultado['descansos_generados']} registros de descanso.",
            'data' => $resultado,
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/reemplazos-disponibles
     * Lista personal disponible para reemplazos
     */
    public function reemplazosDisponibles(Request $request): JsonResponse
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $fecha = Carbon::parse($request->input('fecha'));
        $personal = $this->asistenciaService->getPersonalDisponibleParaReemplazo(
            $fecha,
            $request->input('proyecto_id')
        );

        return response()->json([
            'success' => true,
            'data' => $personal,
            'meta' => [
                'fecha' => $fecha->toDateString(),
                'total_disponible' => $personal->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/operaciones/asistencia/{id}/entrada
     * Marca hora de entrada
     */
    public function marcarEntrada(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'hora' => 'required|date_format:H:i',
        ]);

        $asistencia = OperacionAsistencia::findOrFail($id);

        // Verificar restricción de fecha
        if (!$this->puedeEditarAsistencia($asistencia, $request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede registrar entrada del día anterior. Fecha permitida: ' . Carbon::yesterday()->toDateString(),
            ], 422);
        }

        if ($asistencia->es_descanso) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede marcar entrada en un día de descanso.',
            ], 422);
        }

        try {
            $asistencia->marcarEntrada($request->input('hora'), $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Entrada registrada correctamente.',
                'data' => $asistencia,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->parsearErrorPostgres($e->getMessage()),
            ], 422);
        }
    }

    /**
     * POST /api/v1/operaciones/asistencia/{id}/salida
     * Marca hora de salida
     */
    public function marcarSalida(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'hora' => 'required|date_format:H:i',
        ]);

        $asistencia = OperacionAsistencia::findOrFail($id);

        // Verificar restricción de fecha
        if (!$this->puedeEditarAsistencia($asistencia, $request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede registrar salida del día anterior. Fecha permitida: ' . Carbon::yesterday()->toDateString(),
            ], 422);
        }

        if ($asistencia->es_descanso) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede marcar salida en un día de descanso.',
            ], 422);
        }

        if (!$asistencia->hora_entrada) {
            return response()->json([
                'success' => false,
                'message' => 'Debe registrar la entrada antes de la salida.',
            ], 422);
        }

        try {
            $asistencia->marcarSalida($request->input('hora'), $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Salida registrada correctamente.',
                'data' => $asistencia,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->parsearErrorPostgres($e->getMessage()),
            ], 422);
        }
    }

    /**
     * POST /api/v1/operaciones/asistencia/{id}/ausencia
     * Marca una asistencia como ausencia
     */
    public function marcarAusencia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo_ausencia_id' => 'required|exists:catalogo_motivos_ausencia,id',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $asistencia = OperacionAsistencia::findOrFail($id);

        // Verificar restricción de fecha
        if (!$this->puedeEditarAsistencia($asistencia, $request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede registrar ausencia del día anterior. Fecha permitida: ' . Carbon::yesterday()->toDateString(),
            ], 422);
        }

        if ($asistencia->es_descanso) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede marcar ausencia en un día de descanso.',
            ], 422);
        }

        if ($asistencia->hora_entrada) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede marcar ausencia si ya tiene entrada registrada.',
            ], 422);
        }

        $motivo = MotivoAusencia::findOrFail($request->input('motivo_ausencia_id'));
        $tipoAusencia = $motivo->es_justificada ? 'justificada' : 'injustificada';

        try {
            $asistencia->marcarAusencia(
                $motivo->id,
                $tipoAusencia,
                $request->input('descripcion'),
                $request->user()?->id
            );

            $asistencia->load('motivoAusencia');

            return response()->json([
                'success' => true,
                'message' => 'Ausencia registrada correctamente.',
                'data' => $asistencia,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->parsearErrorPostgres($e->getMessage()),
            ], 422);
        }
    }

    /**
     * GET /api/v1/operaciones/asistencia/motivos-ausencia
     * Lista los motivos de ausencia disponibles
     */
    public function motivosAusencia(): JsonResponse
    {
        $motivos = MotivoAusencia::activos()->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $motivos,
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/vista-agrupada
     * Obtiene vista agrupada de asistencia por proyecto/departamento
     */
    public function vistaAgrupada(Request $request): JsonResponse
    {
        $request->validate([
            'fecha' => 'required|date',
            'agrupar_por' => 'nullable|in:proyecto,departamento',
        ]);

        $fecha = Carbon::parse($request->input('fecha'));
        $agruparPor = $request->input('agrupar_por', 'proyecto');

        $query = OperacionAsistencia::with([
            'asignacion.personal.departamento',
            'asignacion.proyecto',
            'motivoAusencia',
        ])
        ->porFecha($fecha);

        $asistencias = $query->get();

        // Agrupar según el criterio
        if ($agruparPor === 'departamento') {
            $agrupados = $asistencias->groupBy(function ($asistencia) {
                return $asistencia->asignacion?->personal?->departamento_id ?? 0;
            })->map(function ($grupo, $departamentoId) {
                $departamento = $grupo->first()->asignacion?->personal?->departamento;
                return [
                    'id' => $departamentoId,
                    'nombre' => $departamento?->nombre ?? 'Sin departamento',
                    'total' => $grupo->count(),
                    'presentes' => $grupo->where('estado_dia', 'presente')->count(),
                    'tardanzas' => $grupo->where('estado_dia', 'tarde')->count(),
                    'ausentes_justificados' => $grupo->where('estado_dia', 'ausente_justificado')->count(),
                    'ausentes_injustificados' => $grupo->where('estado_dia', 'ausente_injustificado')->count(),
                    'descansos' => $grupo->where('estado_dia', 'descanso')->count(),
                    'sin_registro' => $grupo->where('estado_dia', 'sin_registro')->count(),
                    'asistencias' => $grupo->values(),
                ];
            })->values();
        } else {
            $agrupados = $asistencias->groupBy(function ($asistencia) {
                return $asistencia->asignacion?->proyecto_id ?? 0;
            })->map(function ($grupo, $proyectoId) {
                $proyecto = $grupo->first()->asignacion?->proyecto;
                return [
                    'id' => $proyectoId,
                    'nombre' => $proyecto?->nombre_proyecto ?? 'Sin proyecto',
                    'correlativo' => $proyecto?->correlativo ?? '',
                    'total' => $grupo->count(),
                    'presentes' => $grupo->where('estado_dia', 'presente')->count(),
                    'tardanzas' => $grupo->where('estado_dia', 'tarde')->count(),
                    'ausentes_justificados' => $grupo->where('estado_dia', 'ausente_justificado')->count(),
                    'ausentes_injustificados' => $grupo->where('estado_dia', 'ausente_injustificado')->count(),
                    'descansos' => $grupo->where('estado_dia', 'descanso')->count(),
                    'sin_registro' => $grupo->where('estado_dia', 'sin_registro')->count(),
                    'asistencias' => $grupo->values(),
                ];
            })->values();
        }

        // Resumen general
        $resumen = [
            'fecha' => $fecha->toDateString(),
            'total_registros' => $asistencias->count(),
            'presentes' => $asistencias->where('estado_dia', 'presente')->count(),
            'tardanzas' => $asistencias->where('estado_dia', 'tarde')->count(),
            'ausentes_justificados' => $asistencias->where('estado_dia', 'ausente_justificado')->count(),
            'ausentes_injustificados' => $asistencias->where('estado_dia', 'ausente_injustificado')->count(),
            'descansos' => $asistencias->where('estado_dia', 'descanso')->count(),
            'sin_registro' => $asistencias->where('estado_dia', 'sin_registro')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'agrupado_por' => $agruparPor,
                'grupos' => $agrupados,
                'resumen' => $resumen,
            ],
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/calendario-turno/{personalAsignadoId}
     * Obtiene el calendario de trabajo para un agente asignado
     */
    public function calendarioTurno(Request $request, int $personalAsignadoId): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));

        // Limitar a 90 días
        if ($fechaInicio->diffInDays($fechaFin) > 90) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo es de 90 días.',
            ], 422);
        }

        $asignacion = \App\Models\OperacionPersonalAsignado::with(['turno', 'personal', 'proyecto'])
            ->findOrFail($personalAsignadoId);

        if (!$asignacion->turno_id) {
            return response()->json([
                'success' => false,
                'message' => 'El agente no tiene turno asignado.',
            ], 422);
        }

        $calendario = $this->turnoCalculadorService->generarCalendario(
            $asignacion->turno_id,
            Carbon::parse($asignacion->fecha_inicio),
            $fechaInicio,
            $fechaFin
        );

        return response()->json([
            'success' => true,
            'data' => [
                'personal' => [
                    'id' => $asignacion->personal_id,
                    'nombre' => $asignacion->personal?->nombre_completo,
                ],
                'proyecto' => [
                    'id' => $asignacion->proyecto_id,
                    'nombre' => $asignacion->proyecto?->nombre_proyecto,
                ],
                'turno' => $asignacion->turno,
                'fecha_inicio_asignacion' => $asignacion->fecha_inicio,
                'calendario' => $calendario,
            ],
        ]);
    }

    /**
     * Verifica si se puede editar una asistencia basándose en la fecha.
     * Solo se permite editar la asistencia del día anterior, a menos que sea admin.
     */
    private function puedeEditarAsistencia(OperacionAsistencia $asistencia, ?\App\Models\User $user): bool
    {
        // Si ya fue procesado en planilla, no se puede editar
        if ($asistencia->procesado_planilla) {
            return false;
        }

        // Admin puede editar cualquier fecha
        if ($user && $user->hasRole('admin')) {
            return true;
        }

        // Solo se puede editar el día de ayer
        $ayer = Carbon::yesterday();
        $fechaAsistencia = Carbon::parse($asistencia->fecha_asistencia);

        return $fechaAsistencia->isSameDay($ayer);
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
        if (str_contains($mensaje, 'P0014')) {
            return 'No puede registrar salida sin entrada.';
        }
        if (str_contains($mensaje, 'asistencia_unica_dia')) {
            return 'Ya existe un registro de asistencia para esta fecha.';
        }

        return 'Error al procesar la asistencia.';
    }
}
