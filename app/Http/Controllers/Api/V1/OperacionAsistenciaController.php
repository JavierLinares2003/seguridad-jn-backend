<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\RegistrarAsistenciaRequest;
use App\Http\Requests\Operacion\UpdateAsistenciaRequest;
use App\Models\OperacionAsistencia;
use App\Models\Proyecto;
use App\Services\Operacion\AsistenciaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class OperacionAsistenciaController extends Controller implements HasMiddleware
{
    public function __construct(
        private AsistenciaService $asistenciaService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-operaciones', only: [
                'index', 'show', 'porFecha', 'porProyecto', 'resumen', 'historialPersonal', 'reemplazosDisponibles'
            ]),
            new Middleware('permission:manage-asistencia', only: [
                'store', 'update', 'destroy', 'generarDescansos', 'marcarEntrada', 'marcarSalida'
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
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $asistencia,
        ]);
    }

    /**
     * PUT /api/v1/operaciones/asistencia/{id}
     * Actualiza una asistencia
     */
    public function update(UpdateAsistenciaRequest $request, int $id): JsonResponse
    {
        $asistencia = OperacionAsistencia::findOrFail($id);

        try {
            $asistencia->update(array_merge(
                $request->validated(),
                ['registrado_por_user_id' => $request->user()?->id]
            ));

            $asistencia->refresh()->load([
                'asignacion.personal',
                'asignacion.proyecto',
                'personalReemplazo',
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
     */
    public function porFecha(Request $request, string $fecha): JsonResponse
    {
        $request->validate([
            'proyecto_id' => 'nullable|integer', // 0 or null allowed for unassigned
        ]);

        try {
            $fechaCarbon = Carbon::parse($fecha);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de fecha inválido.',
            ], 422);
        }

        $asistencia = $this->asistenciaService->getAsistenciaPorProyectoYFecha(
            $request->input('proyecto_id') ? (int)$request->input('proyecto_id') : null,
            $fechaCarbon
        );

        return response()->json([
            'success' => true,
            'data' => $asistencia,
            'meta' => [
                'fecha' => $fechaCarbon->toDateString(),
                'proyecto_id' => $request->input('proyecto_id'),
                'total_asignaciones' => $asistencia->count(),
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
