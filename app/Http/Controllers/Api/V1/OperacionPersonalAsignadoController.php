<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\StoreAsignacionRequest;
use App\Http\Requests\Operacion\UpdateAsignacionRequest;
use App\Models\OperacionPersonalAsignado;
use App\Models\Personal;
use App\Models\Proyecto;
use App\Models\ProyectoConfiguracionPersonal;
use App\Services\Operacion\PersonalDisponibilidadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class OperacionPersonalAsignadoController extends Controller implements HasMiddleware
{
    public function __construct(
        private PersonalDisponibilidadService $disponibilidadService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-operaciones', only: ['index', 'show', 'personalDisponible', 'calendario', 'estadisticas', 'proyectosParaAsignar']),
            new Middleware('permission:manage-asignaciones', only: ['store', 'update', 'destroy', 'finalizar', 'suspender', 'reactivar']),
        ];
    }

    /**
     * GET /api/v1/operaciones/asignaciones
     * Lista todas las asignaciones con filtros opcionales
     */
    public function index(Request $request): JsonResponse
    {
        $query = OperacionPersonalAsignado::query()
            ->with(['personal', 'proyecto', 'configuracionPuesto.tipoPersonal', 'turno']);

        // Filtros
        if ($request->filled('proyecto_id')) {
            $query->byProyecto($request->input('proyecto_id'));
        }

        if ($request->filled('personal_id')) {
            $query->byPersonal($request->input('personal_id'));
        }

        if ($request->filled('estado_asignacion')) {
            $query->where('estado_asignacion', $request->input('estado_asignacion'));
        }

        if ($request->filled('solo_vigentes') && $request->boolean('solo_vigentes')) {
            $query->vigentes();
        }

        // Ordenamiento
        $query->orderBy($request->input('orden_campo', 'created_at'), $request->input('orden_dir', 'desc'));

        // Paginación
        $perPage = min($request->input('per_page', 15), 100);
        $asignaciones = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $asignaciones,
        ]);
    }

    /**
     * POST /api/v1/operaciones/asignar-personal
     * Crea una nueva asignación de personal
     */
    public function store(StoreAsignacionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Verificar que la configuración pertenece al proyecto
        if (isset($validated['configuracion_puesto_id']) && isset($validated['proyecto_id'])) {
            $config = ProyectoConfiguracionPersonal::findOrFail($validated['configuracion_puesto_id']);
            if ($config->proyecto_id != $validated['proyecto_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'La configuración del puesto no pertenece al proyecto especificado.',
                ], 422);
            }
        }

        // Verificar que el proyecto esté activo
        if (isset($validated['proyecto_id'])) {
            $proyecto = Proyecto::findOrFail($validated['proyecto_id']);
            if ($proyecto->estado_proyecto === 'finalizado') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden crear asignaciones en un proyecto finalizado.',
                ], 422);
            }
        }

        // Verificar disponibilidad (validación adicional en PHP)
        $fechaInicio = Carbon::parse($validated['fecha_inicio']);
        $fechaFin = isset($validated['fecha_fin']) ? Carbon::parse($validated['fecha_fin']) : null;

        if (!$this->disponibilidadService->estaDisponible($validated['personal_id'], $fechaInicio, $fechaFin)) {
            return response()->json([
                'success' => false,
                'message' => 'El personal tiene una asignación activa que se solapa con las fechas indicadas.',
            ], 422);
        }

        // Verificar requisitos del puesto
        $personal = Personal::findOrFail($validated['personal_id']);
        $requisitos = ['cumple' => true, 'errores' => []];
        
        if (isset($validated['configuracion_puesto_id'])) {
            $requisitos = $this->disponibilidadService->cumpleRequisitos($personal, $validated['configuracion_puesto_id']);
        }

        $warnings = [];
        
        // Si no cumple requisitos, verificar si se está forzando la asignación
        if (!$requisitos['cumple']) {
            // Si no se está forzando, retornar advertencia (con código 200 para que frontend pueda procesar)
            if (!$request->input('force_assignment', false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El personal no cumple con todos los requisitos del puesto.',
                    'errores' => $requisitos['errores'],
                    'detalles' => $requisitos['detalles'] ?? null,
                    'requiere_confirmacion' => true,
                ], 200);
            }

            // Si se está forzando, marcar que se forzaron los requisitos
            $validated['requisitos_forzados'] = true;
            $warnings = $requisitos['errores'];
        }

        try {
            $asignacion = DB::transaction(function () use ($validated, $warnings) {
                $asignacion = OperacionPersonalAsignado::create($validated);
                
                // Si hay advertencias, agregarlas a las notas
                if (!empty($warnings)) {
                    $notasAdvertencia = "⚠️ ASIGNADO CON ADVERTENCIAS:\n" . implode("\n", array_map(fn($w) => "- $w", $warnings));
                    $asignacion->notas = $asignacion->notas 
                        ? $notasAdvertencia . "\n\n" . $asignacion->notas 
                        : $notasAdvertencia;
                    $asignacion->save();
                }
                
                return $asignacion;
            });

            $asignacion->load(['personal', 'proyecto', 'configuracionPuesto', 'turno']);

            return response()->json([
                'success' => true,
                'message' => !empty($warnings) 
                    ? 'Asignación creada con advertencias.' 
                    : 'Asignación creada correctamente.',
                'data' => $asignacion,
                'warnings' => $warnings,
            ], 201);
        } catch (\Exception $e) {
            // Capturar errores de los triggers PostgreSQL
            $mensaje = $this->parsearErrorPostgres($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $mensaje,
            ], 422);
        }
    }

    /**
     * GET /api/v1/operaciones/asignaciones/{id}
     * Muestra una asignación específica
     */
    public function show(int $id): JsonResponse
    {
        $asignacion = OperacionPersonalAsignado::with([
            'personal',
            'proyecto',
            'configuracionPuesto',
            'turno',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $asignacion,
        ]);
    }

    /**
     * PUT /api/v1/operaciones/asignaciones/{id}
     * Actualiza una asignación existente
     */
    public function update(UpdateAsignacionRequest $request, int $id): JsonResponse
    {
        $asignacion = OperacionPersonalAsignado::findOrFail($id);
        $validated = $request->validated();

        // Si cambian las fechas, verificar disponibilidad
        if (isset($validated['fecha_inicio']) || isset($validated['fecha_fin'])) {
            $fechaInicio = Carbon::parse($validated['fecha_inicio'] ?? $asignacion->fecha_inicio);
            $fechaFin = isset($validated['fecha_fin'])
                ? Carbon::parse($validated['fecha_fin'])
                : $asignacion->fecha_fin;

            if (!$this->disponibilidadService->estaDisponible(
                $asignacion->personal_id,
                $fechaInicio,
                $fechaFin,
                $asignacion->id
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cambio de fechas genera conflicto con otra asignación.',
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($asignacion, $validated) {
                $asignacion->update($validated);
            });

            $asignacion->refresh()->load(['personal', 'proyecto', 'configuracionPuesto', 'turno']);

            return response()->json([
                'success' => true,
                'message' => 'Asignación actualizada correctamente.',
                'data' => $asignacion,
            ]);
        } catch (\Exception $e) {
            $mensaje = $this->parsearErrorPostgres($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $mensaje,
            ], 422);
        }
    }

    /**
     * DELETE /api/v1/operaciones/asignaciones/{id}
     * Elimina una asignación
     */
    public function destroy(int $id): JsonResponse
    {
        $asignacion = OperacionPersonalAsignado::findOrFail($id);

        // No permitir eliminar si tiene asistencias registradas
        if ($asignacion->asistencias()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la asignación porque tiene registros de asistencia.',
            ], 422);
        }

        $asignacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asignación eliminada correctamente.',
        ]);
    }

    /**
     * GET /api/v1/operaciones/personal-disponible
     * Lista el personal disponible en un rango de fechas
     */
    public function personalDisponible(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'configuracion_puesto_id' => 'nullable|exists:proyectos_configuracion_personal,id',
        ]);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = $request->filled('fecha_fin')
            ? Carbon::parse($request->input('fecha_fin'))
            : null;

        $personal = $this->disponibilidadService->getPersonalDisponible(
            $fechaInicio,
            $fechaFin,
            $request->input('configuracion_puesto_id')
        );

        return response()->json([
            'success' => true,
            'data' => $personal,
            'meta' => [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin?->toDateString(),
                'total_disponible' => $personal->count(),
            ],
        ]);
    }

    /**
     * GET /api/v1/operaciones/personal/{id}/calendario
     * Obtiene el calendario de disponibilidad de un empleado
     */
    public function calendario(Request $request, int $personalId): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
        ]);

        Personal::findOrFail($personalId);

        $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
        $fechaFin = Carbon::parse($request->input('fecha_fin'));

        // Limitar a máximo 90 días
        if ($fechaInicio->diffInDays($fechaFin) > 90) {
            return response()->json([
                'success' => false,
                'message' => 'El rango máximo es de 90 días.',
            ], 422);
        }

        $calendario = $this->disponibilidadService->getCalendarioDisponibilidad(
            $personalId,
            $fechaInicio,
            $fechaFin
        );

        return response()->json([
            'success' => true,
            'data' => $calendario,
        ]);
    }

    /**
     * GET /api/v1/operaciones/proyectos/{id}/estadisticas
     * Obtiene estadísticas de asignación de un proyecto
     */
    public function estadisticas(int $proyectoId): JsonResponse
    {
        Proyecto::findOrFail($proyectoId);

        $estadisticas = $this->disponibilidadService->getEstadisticasProyecto($proyectoId);

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
        ]);
    }

    /**
     * POST /api/v1/operaciones/asignaciones/{id}/finalizar
     * Finaliza una asignación
     */
    public function finalizar(Request $request, int $id): JsonResponse
    {
        $asignacion = OperacionPersonalAsignado::findOrFail($id);

        if ($asignacion->estado_asignacion !== 'activa') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden finalizar asignaciones activas.',
            ], 422);
        }

        $motivo = $request->input('motivo');
        $asignacion->finalizar($motivo);

        return response()->json([
            'success' => true,
            'message' => 'Asignación finalizada correctamente.',
            'data' => $asignacion,
        ]);
    }

    /**
     * POST /api/v1/operaciones/asignaciones/{id}/suspender
     * Suspende una asignación
     */
    public function suspender(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        $asignacion = OperacionPersonalAsignado::findOrFail($id);

        if ($asignacion->estado_asignacion !== 'activa') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden suspender asignaciones activas.',
            ], 422);
        }

        $asignacion->suspender($request->input('motivo'));

        return response()->json([
            'success' => true,
            'message' => 'Asignación suspendida correctamente.',
            'data' => $asignacion,
        ]);
    }

    /**
     * POST /api/v1/operaciones/asignaciones/{id}/reactivar
     * Reactiva una asignación suspendida
     */
    public function reactivar(int $id): JsonResponse
    {
        $asignacion = OperacionPersonalAsignado::findOrFail($id);

        if ($asignacion->estado_asignacion !== 'suspendida') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden reactivar asignaciones suspendidas.',
            ], 422);
        }

        // Verificar que no haya conflictos
        $fechaFin = $asignacion->fecha_fin;
        if (!$this->disponibilidadService->estaDisponible(
            $asignacion->personal_id,
            Carbon::today(),
            $fechaFin,
            $asignacion->id
        )) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede reactivar porque hay conflicto con otra asignación.',
            ], 422);
        }

        $asignacion->reactivar();

        return response()->json([
            'success' => true,
            'message' => 'Asignación reactivada correctamente.',
            'data' => $asignacion,
        ]);
    }

    /**
     * Lista proyectos con información para asignaciones.
     * Endpoint ligero específico para el módulo de asignaciones.
     *
     * GET /api/v1/operaciones/asignaciones/proyectos
     */
    public function proyectosParaAsignar(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'estado' => 'nullable|string|in:planificacion,activo,suspendido,finalizado',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Proyecto::query()
            ->select([
                'id',
                'correlativo',
                'nombre_proyecto',
                'empresa_cliente',
                'estado_proyecto',
                'fecha_inicio_real',
                'fecha_fin_real',
            ]);

        // Filtrar por estado: si se especifica uno, usar ese; sino, default a planificacion/activo
        if ($request->filled('estado')) {
            $query->where('estado_proyecto', $request->input('estado'));
        } else {
            $query->whereIn('estado_proyecto', ['planificacion', 'activo']);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre_proyecto', 'ilike', "%{$search}%")
                  ->orWhere('correlativo', 'ilike', "%{$search}%")
                  ->orWhere('empresa_cliente', 'ilike', "%{$search}%");
            });
        }

        $proyectos = $query->orderBy('nombre_proyecto')
            ->paginate($request->input('per_page', 15));

        // Agregar información de asignaciones a cada proyecto
        $proyectos->getCollection()->transform(function ($proyecto) {
            // Obtener configuraciones de puestos
            $configuraciones = ProyectoConfiguracionPersonal::with('tipoPersonal')
                ->where('proyecto_id', $proyecto->id)
                ->where('estado', 'activo')
                ->get();

            // Obtener asignaciones activas (incluye futuras para planificación)
            $asignaciones = OperacionPersonalAsignado::with([
                'personal:id,nombres,apellidos,dpi,telefono,foto_perfil',
                'turno:id,nombre,hora_inicio,hora_fin',
                'configuracionPuesto:id,nombre_puesto',
            ])
                ->where('proyecto_id', $proyecto->id)
                ->where('estado_asignacion', 'activa')
                ->get();

            // Calcular estadísticas por puesto
            $puestos = $configuraciones->map(function ($config) use ($asignaciones) {
                $asignadosEnPuesto = $asignaciones->where('configuracion_puesto_id', $config->id);

                return [
                    'configuracion_id' => $config->id,
                    'nombre_puesto' => $config->nombre_puesto,
                    'tipo_personal' => $config->tipoPersonal?->nombre,
                    'cantidad_requerida' => $config->cantidad_requerida,
                    'cantidad_asignada' => $asignadosEnPuesto->count(),
                    'faltantes' => max(0, $config->cantidad_requerida - $asignadosEnPuesto->count()),
                    'asignados' => $asignadosEnPuesto->map(fn($a) => [
                        'asignacion_id' => $a->id,
                        'personal' => $a->personal ? [
                            'id' => $a->personal->id,
                            'nombre_completo' => $a->personal->nombre_completo,
                            'dpi' => $a->personal->dpi,
                            'telefono' => $a->personal->telefono,
                            'iniciales' => $a->personal->iniciales,
                            'foto_url' => $a->personal->foto_url,
                        ] : null,
                        'turno' => $a->turno ? [
                            'id' => $a->turno->id,
                            'nombre' => $a->turno->nombre,
                            'horario' => $a->turno->hora_inicio . ' - ' . $a->turno->hora_fin,
                        ] : null,
                        'fecha_inicio' => $a->fecha_inicio?->toDateString(),
                        'fecha_fin' => $a->fecha_fin?->toDateString(),
                    ])->values(),
                ];
            });

            return [
                'id' => $proyecto->id,
                'correlativo' => $proyecto->correlativo,
                'nombre' => $proyecto->nombre_proyecto,
                'empresa_cliente' => $proyecto->empresa_cliente,
                'estado' => $proyecto->estado_proyecto,
                'puestos' => $puestos,
                'resumen' => [
                    'total_requerido' => $puestos->sum('cantidad_requerida'),
                    'total_asignado' => $puestos->sum('cantidad_asignada'),
                    'total_faltantes' => $puestos->sum('faltantes'),
                    'porcentaje_cubierto' => $puestos->sum('cantidad_requerida') > 0
                        ? round(($puestos->sum('cantidad_asignada') / $puestos->sum('cantidad_requerida')) * 100, 1)
                        : 100,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $proyectos,
        ]);
    }

    /**
     * Parsea mensajes de error de PostgreSQL para mostrar mensajes amigables.
     */
    private function parsearErrorPostgres(string $mensaje): string
    {
        // Buscar el mensaje entre comillas del RAISE EXCEPTION
        if (preg_match('/ERROR:\s*(.+?)(?:\s*CONTEXT:|$)/s', $mensaje, $matches)) {
            return trim($matches[1]);
        }

        // Si contiene códigos específicos de nuestros triggers
        if (str_contains($mensaje, 'P0001')) {
            return 'El personal ya tiene una asignación activa que se solapa con estas fechas.';
        }
        if (str_contains($mensaje, 'P0007')) {
            return 'El personal no cumple con los requisitos del puesto.';
        }
        if (str_contains($mensaje, 'P0008')) {
            return 'El puesto ya tiene la cantidad máxima de personal asignado.';
        }

        return 'Error al procesar la asignación.';
    }
}
