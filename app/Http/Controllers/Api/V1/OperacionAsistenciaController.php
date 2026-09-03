<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\RegistrarAsistenciaRequest;
use App\Http\Requests\Operacion\UpdateAsistenciaRequest;
use App\Models\Catalogos\MotivoAusencia;
use App\Models\OperacionAsistencia;
use App\Models\Personal;
use App\Models\PersonalPermiso;
use App\Models\Proyecto;
use App\Services\Operacion\AsistenciaService;
use App\Services\TurnoCalculadorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

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
                'reemplazosDisponibles', 'vistaAgrupada', 'motivosAusencia', 'calendarioTurno',
            ]),
            new Middleware('permission:view-asistencia-administrativa', only: ['administrativaPorFecha']),
            new Middleware('permission:view-operaciones|view-asistencia-administrativa', only: ['calendarioPersonal']),
            new Middleware('permission:manage-asistencia|manage-asistencia-administrativa', only: [
                'store', 'update', 'destroy',
            ]),
            new Middleware('permission:manage-asistencia', only: [
                'generarDescansos', 'marcarAusencia', 'permisosDisponibles'
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
        $user = $request->user();
        $bloqueo = $this->autorizarAsistenciasAdministrativas($user, $request->input('asistencias', []));
        if ($bloqueo) {
            return $bloqueo;
        }

        $userId = $user?->id;
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
            'per_page' => 'nullable|integer|min:1|max:200',
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
                $request->input('departamento_id'),
                $request->input('buscar')
            );
        }

        // CASO 2: Búsqueda por ID específico de personal
        if ($request->filled('personal_id')) {
            return $this->buscarPersonalEnFecha($request, $fechaCarbon);
        }

        // CASO 3: Proyecto específico (con filtro de nombre opcional)
        if ($request->filled('proyecto_id')) {
            $asistencia = $this->asistenciaService->getAsistenciaPorProyectoYFecha(
                (int) $request->input('proyecto_id'),
                $fechaCarbon,
                $request->input('buscar')
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
        return $this->getProyectosConPersonal($fechaCarbon, $request->input('per_page', 10), $request->input('buscar'));
    }

    /**
     * Retorna proyectos con su personal asignado para una fecha, paginado por proyectos.
     * Criterio: hay asignación vigente ese día. No se filtra por fechas del contrato
     * (si hay gente en el puesto, el proyecto debe salir en el listado).
     */
    private function getProyectosConPersonal(Carbon $fecha, int $perPage, ?string $buscar = null): JsonResponse
    {
        $proyectosConAsignaciones = \App\Models\OperacionPersonalAsignado::vigentes($fecha)
            ->whereNotNull('proyecto_id')
            ->when($buscar, fn ($q) => $q->whereHas('personal', fn ($pq) => $pq->buscar($buscar)))
            ->distinct()
            ->pluck('proyecto_id');

        $proyectosPaginados = Proyecto::query()
            ->whereIn('id', $proyectosConAsignaciones)
            ->whereIn('estado_proyecto', ['planificacion', 'activo'])
            ->orderBy('nombre_proyecto')
            ->orderBy('id')
            ->paginate($perPage);

        // Para cada proyecto, obtener su personal con asistencia
        $proyectosConPersonal = $proyectosPaginados->getCollection()->map(function ($proyecto) use ($fecha) {
            $asignaciones = \App\Models\OperacionPersonalAsignado::with([
                'personal',
                'turno',
                'configuracionPuesto.tipoPersonal',
            ])
            ->where('proyecto_id', $proyecto->id)
            ->vigentes($fecha)
            ->get();

            // Obtener asistencias para estas asignaciones
            $asistencias = OperacionAsistencia::with(['personalReemplazo', 'motivoAusencia', 'permisoReposicion'])
                ->whereIn('personal_asignado_id', $asignaciones->pluck('id'))
                ->where('fecha_asistencia', $fecha)
                ->get()
                ->keyBy('personal_asignado_id');

            $coberturas = $this->asistenciaService->coberturasDelDia(
                $fecha,
                $asignaciones->pluck('personal_id')->all()
            );

            // Combinar asignaciones con asistencias
            $personal = $asignaciones->map(function ($asignacion) use ($asistencias, $coberturas) {
                $asistencia = $asistencias->get($asignacion->id);
                $cubrio = $coberturas->get($asignacion->personal_id);
                return [
                    'asignacion_id' => $asignacion->id,
                    'personal' => $asignacion->personal,
                    'turno' => $asignacion->turno,
                    'puesto' => $asignacion->configuracionPuesto?->nombre_puesto
                        ?? $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                    'tipo_personal' => $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                    'asistencia' => $asistencia ? [
                        'id' => $asistencia->id,
                        'estado' => $asistencia->estado_dia,
                        'es_descanso' => $asistencia->es_descanso,
                        'es_extra' => $asistencia->es_extra,
                        'es_ausente' => $asistencia->es_ausente,
                        'motivo_ausencia' => $asistencia->motivoAusencia,
                        'descripcion_ausencia' => $asistencia->descripcion_ausencia,
                        'tipo_ausencia' => $asistencia->tipo_ausencia,
                        'fue_reemplazado' => $asistencia->fue_reemplazado,
                        'reemplazo' => $asistencia->personalReemplazo,
                        'hizo_reposicion' => $asistencia->permiso_reposicion_id !== null,
                        'horas_reposicion' => $asistencia->horas_reposicion,
                        'permiso_reposicion' => $asistencia->permisoReposicion ? [
                            'id'                => $asistencia->permisoReposicion->id,
                            'tipo'              => $asistencia->permisoReposicion->tipo,
                            'descripcion'       => $asistencia->permisoReposicion->descripcion,
                            'cantidad_aprobada' => $asistencia->permisoReposicion->cantidad_aprobada,
                            'saldo_pendiente'   => $asistencia->permisoReposicion->saldo_pendiente,
                        ] : null,
                        'observaciones' => $asistencia->observaciones,
                        'cubrio_en' => $cubrio ? [
                            'proyecto' => $cubrio->proyectoCobertura?->nombre_proyecto,
                            'titular' => $cubrio->asistenciaTitular?->asignacion?->personal?->nombre_completo,
                        ] : null,
                    ] : ['id' => null, 'estado' => 'sin_registro', 'cubrio_en' => $cubrio ? [
                        'proyecto' => $cubrio->proyectoCobertura?->nombre_proyecto,
                        'titular' => $cubrio->asistenciaTitular?->asignacion?->personal?->nombre_completo,
                    ] : null],
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
                    'telefono' => $proyecto->telefono,
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
     * Busca personal por ID o nombre, retorna TODO el personal del proyecto al que pertenece.
     */
    private function buscarPersonalEnFecha(Request $request, Carbon $fecha): JsonResponse
    {
        $query = \App\Models\OperacionPersonalAsignado::vigentes($fecha)
            ->whereNotNull('proyecto_id');

        if ($request->filled('personal_id')) {
            $query->where('personal_id', $request->input('personal_id'));
        }

        if ($request->filled('buscar')) {
            $tokens = array_values(array_filter(explode(' ', trim($request->input('buscar')))));
            $query->whereHas('personal', function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $like = '%' . $token . '%';
                    $q->where(function ($inner) use ($like) {
                        $inner->whereRaw("unaccent(nombres) ilike unaccent(?)", [$like])
                              ->orWhereRaw("unaccent(apellidos) ilike unaccent(?)", [$like])
                              ->orWhere('dpi', 'like', $like);
                    });
                }
            });
        }

        $proyectoIds = $query->pluck('proyecto_id')->unique()->values();

        if ($proyectoIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'fecha' => $fecha->toDateString(),
                    'total_proyectos' => 0,
                ],
            ]);
        }

        $proyectos = Proyecto::whereIn('id', $proyectoIds)->orderBy('nombre_proyecto')->get();

        $resultado = $proyectos->map(function ($proyecto) use ($fecha) {
            $asignaciones = \App\Models\OperacionPersonalAsignado::with([
                'personal',
                'turno',
                'configuracionPuesto.tipoPersonal',
            ])
            ->where('proyecto_id', $proyecto->id)
            ->vigentes($fecha)
            ->get();

            $asistencias = OperacionAsistencia::with(['personalReemplazo', 'motivoAusencia', 'permisoReposicion'])
                ->whereIn('personal_asignado_id', $asignaciones->pluck('id'))
                ->where('fecha_asistencia', $fecha)
                ->get()
                ->keyBy('personal_asignado_id');

            $coberturas = $this->asistenciaService->coberturasDelDia(
                $fecha,
                $asignaciones->pluck('personal_id')->all()
            );

            $personal = $asignaciones->map(function ($asignacion) use ($asistencias, $coberturas) {
                $asistencia = $asistencias->get($asignacion->id);
                $cubrio = $coberturas->get($asignacion->personal_id);
                $cubrioEn = $cubrio ? [
                    'proyecto' => $cubrio->proyectoCobertura?->nombre_proyecto,
                    'titular' => $cubrio->asistenciaTitular?->asignacion?->personal?->nombre_completo,
                ] : null;
                return [
                    'asignacion_id' => $asignacion->id,
                    'personal'      => $asignacion->personal,
                    'turno'         => $asignacion->turno,
                    'puesto'        => $asignacion->configuracionPuesto?->nombre_puesto
                                        ?? $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                    'tipo_personal' => $asignacion->configuracionPuesto?->tipoPersonal?->nombre,
                    'asistencia'    => $asistencia ? [
                        'id'                   => $asistencia->id,
                        'estado'               => $asistencia->estado_dia,
                        'es_descanso'          => $asistencia->es_descanso,
                        'es_extra'             => $asistencia->es_extra,
                        'es_ausente'           => $asistencia->es_ausente,
                        'motivo_ausencia'      => $asistencia->motivoAusencia,
                        'descripcion_ausencia' => $asistencia->descripcion_ausencia,
                        'tipo_ausencia'        => $asistencia->tipo_ausencia,
                        'fue_reemplazado'      => $asistencia->fue_reemplazado,
                        'reemplazo'            => $asistencia->personalReemplazo,
                        'hizo_reposicion'      => $asistencia->permiso_reposicion_id !== null,
                        'horas_reposicion'     => $asistencia->horas_reposicion,
                        'permiso_reposicion'   => $asistencia->permisoReposicion ? [
                            'id'                => $asistencia->permisoReposicion->id,
                            'tipo'              => $asistencia->permisoReposicion->tipo,
                            'descripcion'       => $asistencia->permisoReposicion->descripcion,
                            'cantidad_aprobada' => $asistencia->permisoReposicion->cantidad_aprobada,
                            'saldo_pendiente'   => $asistencia->permisoReposicion->saldo_pendiente,
                        ] : null,
                        'observaciones'        => $asistencia->observaciones,
                        'cubrio_en'            => $cubrioEn,
                    ] : ['id' => null, 'estado' => 'sin_registro', 'cubrio_en' => $cubrioEn],
                ];
            });

            $resumen = [
                'total'                   => $personal->count(),
                'presentes'               => $personal->where('asistencia.estado', 'presente')->count(),
                'tardanzas'               => $personal->where('asistencia.estado', 'tarde')->count(),
                'ausentes_justificados'   => $personal->where('asistencia.estado', 'ausente_justificado')->count(),
                'ausentes_injustificados' => $personal->where('asistencia.estado', 'ausente_injustificado')->count(),
                'descansos'               => $personal->where('asistencia.estado', 'descanso')->count(),
                'sin_registro'            => $personal->where('asistencia.estado', 'sin_registro')->count(),
            ];

            return [
                'proyecto' => [
                    'id'              => $proyecto->id,
                    'nombre'          => $proyecto->nombre_proyecto,
                    'correlativo'     => $proyecto->correlativo,
                    'empresa_cliente' => $proyecto->empresa_cliente,
                    'telefono'        => $proyecto->telefono,
                ],
                'personal' => $personal->values(),
                'resumen'  => $resumen,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $resultado->values(),
            'meta'    => [
                'fecha'           => $fecha->toDateString(),
                'total_proyectos' => $resultado->count(),
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
    private function getPersonalSinAsignar(Carbon $fecha, int $perPage = 15, ?int $departamentoId = null, ?string $buscar = null): JsonResponse
    {
        // Personal activo que NO tiene asignación activa en esta fecha
        $personalConAsignacion = \App\Models\OperacionPersonalAsignado::vigentes($fecha)
            ->pluck('personal_id');

        // CASO 1: Vista detallada de un departamento específico con paginación
        if ($departamentoId !== null) {
            return $this->getPersonalSinAsignarPorDepartamento($fecha, $departamentoId, $perPage, $personalConAsignacion, $buscar);
        }

        // CASO 2: Vista general de todos los departamentos
        return $this->getPersonalSinAsignarVistaGeneral($fecha, $personalConAsignacion, $buscar);
    }

    /**
     * Vista detallada: Un departamento con paginación de su personal
     */
    private function getPersonalSinAsignarPorDepartamento(
        Carbon $fecha,
        int $departamentoId,
        int $perPage,
        $personalConAsignacion,
        ?string $buscar = null
    ): JsonResponse {
        $personalQuery = \App\Models\Personal::with(['departamento'])
            ->operativo()
            ->where('estado', 'activo')
            ->whereNotIn('id', $personalConAsignacion)
            ->where('departamento_id', $departamentoId)
            ->buscar($buscar);

        $totalRegistros = $personalQuery->count();
        $personalPaginado = $personalQuery->paginate($perPage);
        $personal = collect($personalPaginado->items());

        // Obtener asistencias
        $asistenciasDirectas = OperacionAsistencia::whereNull('personal_asignado_id')
            ->whereIn('personal_id', $personal->pluck('id'))
            ->where('fecha_asistencia', $fecha)
            ->with(['motivoAusencia', 'permisoReposicion'])
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
                    'es_extra' => $asistencia->es_extra,
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
        $personalConAsignacion,
        ?string $buscar = null
    ): JsonResponse {
        $limitePorDepartamento = 10;

        // Obtener todos los departamentos que tienen personal sin asignar
        $departamentos = \App\Models\Catalogos\Departamento::whereHas('personal', function ($q) use ($personalConAsignacion, $buscar) {
            $q->where('estado', 'activo')
              ->operativo()
              ->whereNotIn('id', $personalConAsignacion)
              ->buscar($buscar);
        })->orderBy('nombre')->get();

        $resultado = [];

        foreach ($departamentos as $departamento) {
            // Obtener personal de este departamento (limitado)
            $personalQuery = \App\Models\Personal::with(['departamento'])
                ->operativo()
                ->where('estado', 'activo')
                ->whereNotIn('id', $personalConAsignacion)
                ->where('departamento_id', $departamento->id)
                ->buscar($buscar);

            $totalEnDepartamento = $personalQuery->count();
            $personal = $personalQuery->limit($limitePorDepartamento)->get();

            // Obtener asistencias para este personal
            $asistenciasDirectas = OperacionAsistencia::whereNull('personal_asignado_id')
                ->whereIn('personal_id', $personal->pluck('id'))
                ->where('fecha_asistencia', $fecha)
                ->with(['motivoAusencia', 'permisoReposicion'])
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
                        'es_extra' => $asistencia->es_extra,
                        'es_ausente' => $asistencia->es_ausente,
                        'motivo_ausencia' => $asistencia->motivoAusencia,
                        'hizo_reposicion' => $asistencia->permiso_reposicion_id !== null,
                        'horas_reposicion' => $asistencia->horas_reposicion,
                        'permiso_reposicion' => $asistencia->permisoReposicion ? [
                            'id'                => $asistencia->permisoReposicion->id,
                            'tipo'              => $asistencia->permisoReposicion->tipo,
                            'descripcion'       => $asistencia->permisoReposicion->descripcion,
                            'cantidad_aprobada' => $asistencia->permisoReposicion->cantidad_aprobada,
                            'saldo_pendiente'   => $asistencia->permisoReposicion->saldo_pendiente,
                        ] : null,
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

        $sinDeptoQuery = \App\Models\Personal::query()
            ->where('estado', 'activo')
            ->whereNotIn('id', $personalConAsignacion)
            ->whereNull('departamento_id')
            ->buscar($buscar);

        $totalSinDepto = $sinDeptoQuery->count();
        if ($totalSinDepto > 0) {
            $personal = (clone $sinDeptoQuery)->limit($limitePorDepartamento)->get();
            $asistenciasDirectas = OperacionAsistencia::whereNull('personal_asignado_id')
                ->whereIn('personal_id', $personal->pluck('id'))
                ->where('fecha_asistencia', $fecha)
                ->with(['motivoAusencia', 'permisoReposicion'])
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
                        'es_extra' => $asistencia->es_extra,
                        'es_ausente' => $asistencia->es_ausente,
                        'motivo_ausencia' => $asistencia->motivoAusencia,
                        'hizo_reposicion' => $asistencia->permiso_reposicion_id !== null,
                        'horas_reposicion' => $asistencia->horas_reposicion,
                        'permiso_reposicion' => $asistencia->permisoReposicion ? [
                            'id'                => $asistencia->permisoReposicion->id,
                            'tipo'              => $asistencia->permisoReposicion->tipo,
                            'descripcion'       => $asistencia->permisoReposicion->descripcion,
                            'cantidad_aprobada' => $asistencia->permisoReposicion->cantidad_aprobada,
                            'saldo_pendiente'   => $asistencia->permisoReposicion->saldo_pendiente,
                        ] : null,
                        'observaciones' => $asistencia->observaciones,
                    ] : ['id' => null, 'estado' => 'sin_registro'],
                ];
            })->values();

            $resultado[] = [
                'departamento' => [
                    'id' => null,
                    'nombre' => 'Sin Departamento',
                ],
                'personal' => $personalData,
                'total_en_departamento' => $totalSinDepto,
                'mostrando' => $personal->count(),
                'hay_mas' => $totalSinDepto > $limitePorDepartamento,
                'resumen' => [
                    'con_asistencia' => $asistenciasDirectas->count(),
                    'sin_registro' => $personal->count() - $asistenciasDirectas->count(),
                ],
            ];
        }

        // Calcular totales generales
        $totalGeneral = \App\Models\Personal::where('estado', 'activo')
            ->operativo()
            ->whereNotIn('id', $personalConAsignacion)
            ->buscar($buscar)
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
        $personalConAsignacion = \App\Models\OperacionPersonalAsignado::vigentes($fechaCarbon)
            ->pluck('personal_id');

        // Obtener departamentos con conteo de personal sin asignar
        $departamentos = \App\Models\Catalogos\Departamento::whereHas('personal', function ($q) use ($personalConAsignacion) {
            $q->where('estado', 'activo')
              ->operativo()
              ->whereNotIn('id', $personalConAsignacion);
        })
        ->withCount(['personal' => function ($q) use ($personalConAsignacion) {
            $q->where('estado', 'activo')
              ->operativo()
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
            ->operativo()
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
            'proyecto_id' => 'nullable|integer',
            'excluir_personal_id' => 'nullable|integer',
        ]);

        $fecha = Carbon::parse($request->input('fecha'));
        $personal = $this->asistenciaService->getPersonalDisponibleParaReemplazo(
            $fecha,
            $request->input('proyecto_id') ? (int) $request->input('proyecto_id') : null,
            $request->input('excluir_personal_id') ? (int) $request->input('excluir_personal_id') : null
        );

        $data = $personal->map(fn (Personal $p) => [
            'id' => $p->id,
            'nombres' => $p->nombres,
            'apellidos' => $p->apellidos,
            'dpi' => $p->dpi,
            'estado' => $p->estado,
            'origen_cobertura' => $p->origen_cobertura ?? 'disponible',
            'proyecto_origen' => $p->proyecto_origen ?? null,
            'puesto_origen' => $p->puesto_origen ?? null,
            'departamento' => $p->departamento,
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'fecha' => $fecha->toDateString(),
                'total_disponible' => $data->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/operaciones/asistencia/{id}/ausencia
     * Marca una asistencia como ausencia
     */
    public function marcarAusencia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo_ausencia_id' => 'nullable|exists:catalogo_motivos_ausencia,id',
            'tipo_inasistencia'  => ['required', Rule::in(['12_horas', '24_horas'])],
            'descripcion'        => 'nullable|string|max:500',
            'permiso_id'         => 'nullable|exists:personal_permisos,id',
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

        $permisoId    = null;
        $tipoAusencia = null;

        if ($request->filled('permiso_id')) {
            $personalId = $asistencia->getPersonalIdEfectivo();
            $permiso    = PersonalPermiso::where('id', $request->input('permiso_id'))
                ->where('personal_id', $personalId)
                ->vigentesEn($asistencia->fecha_asistencia->toDateString())
                ->first();

            if (! $permiso) {
                return response()->json([
                    'success' => false,
                    'message' => 'El permiso no es válido para este empleado o no está vigente en esta fecha.',
                ], 422);
            }

            $permisoId    = $permiso->id;
            $tipoAusencia = 'justificada';
        }

        try {
            $asistencia->marcarAusencia(
                $request->input('motivo_ausencia_id'),
                $tipoAusencia,
                $request->input('descripcion'),
                $request->input('tipo_inasistencia'),
                $request->user()?->id,
                $permisoId
            );

            $asistencia->load('motivoAusencia', 'permisoAusencia');

            return response()->json([
                'success' => true,
                'message' => 'Ausencia registrada correctamente.',
                'data'    => $asistencia,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->parsearErrorPostgres($e->getMessage()),
            ], 422);
        }
    }

    /**
     * GET /api/v1/operaciones/asistencia/{id}/permisos-disponibles
     * Retorna permisos aprobados vigentes del empleado en la fecha de la asistencia.
     */
    public function permisosDisponibles(int $id): JsonResponse
    {
        $asistencia = OperacionAsistencia::findOrFail($id);
        $personalId = $asistencia->getPersonalIdEfectivo();

        if (! $personalId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $fecha    = $asistencia->fecha_asistencia->toDateString();
        $permisos = PersonalPermiso::where('personal_id', $personalId)
            ->vigentesEn($fecha)
            ->orderBy('fecha_inicio')
            ->get()
            ->map(fn ($p) => [
                'id'                => $p->id,
                'tipo'              => $p->tipo,
                'cantidad_aprobada' => $p->cantidad_aprobada,
                'saldo_pendiente'   => $p->saldo_pendiente,
                'fecha_inicio'      => $p->fecha_inicio?->format('Y-m-d'),
                'fecha_fin'         => $p->fecha_fin?->format('Y-m-d'),
                'descripcion'       => $p->descripcion,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $permisos,
        ]);
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
                    'telefono' => $proyecto?->telefono,
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

        $fechaInicioAsignacion = Carbon::parse($asignacion->fecha_inicio)->startOfDay();
        $fechaFinAsignacion = $asignacion->fecha_fin
            ? Carbon::parse($asignacion->fecha_fin)->startOfDay()
            : null;

        $calendario = $this->turnoCalculadorService->generarCalendario(
            $asignacion->turno_id,
            $fechaInicioAsignacion,
            $fechaInicio,
            $fechaFin,
            $fechaFinAsignacion
        );

        $historial = $this->asistenciaService->incorporarHistorialPuestos(
            $calendario,
            $asignacion->personal_id,
            $asignacion->id,
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
                'puestos_anteriores' => $historial['puestos_anteriores'],
                'calendario' => $historial['calendario'],
            ],
        ]);
    }

    /**
     * GET /api/v1/operaciones/asistencia/calendario-personal/{personalId}
     * Calendario de días trabajados de un agente, con o sin puesto asignado.
     */
    public function calendarioPersonal(Request $request, int $personalId): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));

        if ($fechaInicio->diffInDays($fechaFin) > 90) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo es de 90 días.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->asistenciaService->getCalendarioDiasTrabajados(
                $personalId,
                $fechaInicio,
                $fechaFin
            ),
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

    public function administrativaPorFecha(Request $request, string $fecha): JsonResponse
    {
        try {
            $fechaCarbon = Carbon::parse($fecha);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de fecha inválido.',
            ], 422);
        }

        $personal = Personal::query()
            ->administrativo()
            ->whereIn('estado', ['activo', 'extrero'])
            ->buscar($request->input('buscar'))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'puesto', 'estado', 'departamento_id']);

        $asistencias = OperacionAsistencia::query()
            ->whereNull('personal_asignado_id')
            ->whereIn('personal_id', $personal->pluck('id'))
            ->whereDate('fecha_asistencia', $fechaCarbon)
            ->with(['motivoAusencia'])
            ->get()
            ->keyBy('personal_id');

        $data = $personal->map(function (Personal $p) use ($asistencias) {
            $asistencia = $asistencias->get($p->id);
            return [
                'id' => $p->id,
                'nombre_completo' => $p->nombre_completo,
                'puesto' => $p->puesto,
                'asistencia' => $asistencia ? [
                    'id' => $asistencia->id,
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
            'data' => $data,
            'meta' => [
                'fecha' => $fechaCarbon->toDateString(),
                'total' => $data->count(),
            ],
        ]);
    }

    private function autorizarAsistenciasAdministrativas($user, array $asistencias): ?JsonResponse
    {
        foreach ($asistencias as $item) {
            $personal = null;
            if (!empty($item['personal_id'])) {
                $personal = Personal::find($item['personal_id']);
            } elseif (!empty($item['personal_asignado_id'])) {
                $asignacion = \App\Models\OperacionPersonalAsignado::find($item['personal_asignado_id']);
                $personal = $asignacion?->personal;
            }

            if (!$personal) {
                continue;
            }

            if ($personal->es_administrativo && !$user?->can('manage-asistencia-administrativa')) {
                return response()->json([
                    'success' => false,
                    'message' => 'La asistencia de administrativos solo la registra gerencia o recursos humanos.',
                ], 403);
            }

            if (!$personal->es_administrativo && !$user?->can('manage-asistencia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puede registrar asistencia operativa.',
                ], 403);
            }
        }

        return null;
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
        if (str_contains($mensaje, 'P0014')) {
            return 'No puede registrar salida sin entrada.';
        }
        if (str_contains($mensaje, 'asistencia_unica_dia')) {
            return 'Ya existe un registro de asistencia para esta fecha.';
        }

        return 'Error al procesar la asistencia.';
    }
}
