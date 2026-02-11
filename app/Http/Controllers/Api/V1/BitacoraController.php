<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Activitylog\Models\Activity;

class BitacoraController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-bitacora'),
        ];
    }

    /**
     * List all activity logs with filters.
     *
     * GET /api/v1/bitacora
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        // Filter by date range
        if ($request->has('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        // Filter by user (causer)
        if ($request->has('usuario_id')) {
            $query->where('causer_id', $request->usuario_id)
                  ->where('causer_type', 'App\\Models\\User');
        }

        // Filter by model type
        if ($request->has('modelo')) {
            $modelClass = $this->getModelClass($request->modelo);
            if ($modelClass) {
                $query->where('subject_type', $modelClass);
            }
        }

        // Filter by specific model ID
        if ($request->has('modelo_id')) {
            $query->where('subject_id', $request->modelo_id);
        }

        // Filter by action/event
        if ($request->has('accion')) {
            $query->where('event', $request->accion);
        }

        // Filter by module
        if ($request->has('modulo')) {
            $query->where('modulo', $request->modulo);
        }

        // Filter by log name
        if ($request->has('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        // Search in description
        if ($request->has('search')) {
            $query->where('description', 'ilike', "%{$request->search}%");
        }

        $activities = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities->map(fn($activity) => $this->formatActivity($activity)),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Show a specific activity log.
     *
     * GET /api/v1/bitacora/{id}
     */
    public function show(int $id): JsonResponse
    {
        $activity = Activity::with(['causer', 'subject'])->find($id);

        if (!$activity) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatActivity($activity, true),
        ]);
    }

    /**
     * Get activities for a specific model type.
     *
     * GET /api/v1/bitacora/modelo/{modelo}
     */
    public function porModelo(Request $request, string $modelo): JsonResponse
    {
        $modelClass = $this->getModelClass($modelo);

        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'message' => 'Modelo no válido.',
            ], 400);
        }

        $query = Activity::with(['causer'])
            ->where('subject_type', $modelClass)
            ->orderBy('created_at', 'desc');

        // Optional: filter by specific ID
        if ($request->has('id')) {
            $query->where('subject_id', $request->id);
        }

        $activities = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities->map(fn($activity) => $this->formatActivity($activity)),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Get activities by a specific user.
     *
     * GET /api/v1/bitacora/usuario/{id}
     */
    public function porUsuario(Request $request, int $userId): JsonResponse
    {
        $query = Activity::with(['subject'])
            ->where('causer_id', $userId)
            ->where('causer_type', 'App\\Models\\User')
            ->orderBy('created_at', 'desc');

        $activities = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities->map(fn($activity) => $this->formatActivity($activity)),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Get activity statistics.
     *
     * GET /api/v1/bitacora/estadisticas
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $fechaDesde = $request->get('fecha_desde', now()->subDays(30)->toDateString());
        $fechaHasta = $request->get('fecha_hasta', now()->toDateString());

        // Activities by action type
        $porAccion = Activity::whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->selectRaw('event, count(*) as total')
            ->groupBy('event')
            ->pluck('total', 'event');

        // Activities by module
        $porModulo = Activity::whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->whereNotNull('modulo')
            ->selectRaw('modulo, count(*) as total')
            ->groupBy('modulo')
            ->pluck('total', 'modulo');

        // Activities by user (top 10)
        $porUsuario = Activity::whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->whereNotNull('causer_id')
            ->with('causer:id,name')
            ->selectRaw('causer_id, count(*) as total')
            ->groupBy('causer_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'usuario_id' => $item->causer_id,
                'usuario' => $item->causer?->name ?? 'Sistema',
                'total' => $item->total,
            ]);

        // Daily activity count
        $porDia = Activity::whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->selectRaw('DATE(created_at) as fecha, count(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->pluck('total', 'fecha');

        return response()->json([
            'success' => true,
            'data' => [
                'periodo' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                ],
                'por_accion' => $porAccion,
                'por_modulo' => $porModulo,
                'por_usuario' => $porUsuario,
                'por_dia' => $porDia,
                'total_registros' => Activity::whereBetween('created_at', [$fechaDesde, $fechaHasta])->count(),
            ],
        ]);
    }

    /**
     * Get available modules and log names.
     *
     * GET /api/v1/bitacora/filtros
     */
    public function filtros(): JsonResponse
    {
        $modulos = Activity::whereNotNull('modulo')
            ->distinct()
            ->pluck('modulo');

        $logNames = Activity::distinct()
            ->pluck('log_name');

        $acciones = ['created', 'updated', 'deleted'];

        $modelos = [
            'user' => 'Usuarios',
            'personal' => 'Personal',
            'proyecto' => 'Proyectos',
            'asignacion' => 'Asignaciones',
            'asistencia' => 'Asistencia',
            'prestamo' => 'Préstamos',
            'transaccion' => 'Transacciones',
            'planilla' => 'Planillas',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'modulos' => $modulos,
                'log_names' => $logNames,
                'acciones' => $acciones,
                'modelos' => $modelos,
            ],
        ]);
    }

    /**
     * Format activity for response.
     */
    private function formatActivity(Activity $activity, bool $detailed = false): array
    {
        $data = [
            'id' => $activity->id,
            'descripcion' => $activity->description,
            'accion' => $activity->event,
            'modelo' => class_basename($activity->subject_type ?? ''),
            'modelo_id' => $activity->subject_id,
            'modulo' => $activity->modulo,
            'log_name' => $activity->log_name,
            'usuario' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name,
            ] : null,
            'ip_address' => $activity->ip_address,
            'fecha' => $activity->created_at->toISOString(),
            'fecha_formato' => $activity->created_at->format('d/m/Y H:i:s'),
        ];

        if ($detailed) {
            $data['cambios'] = $activity->properties->toArray();
            $data['user_agent'] = $activity->user_agent;
            $data['subject'] = $activity->subject;
        } else {
            // For list view, show summary of changes
            $properties = $activity->properties;
            if ($properties->has('attributes') && $properties->has('old')) {
                $data['campos_modificados'] = array_keys(
                    array_diff_assoc(
                        $properties->get('attributes', []),
                        $properties->get('old', [])
                    )
                );
            }
        }

        return $data;
    }

    /**
     * Get model class from alias.
     */
    private function getModelClass(string $alias): ?string
    {
        $models = [
            'user' => 'App\\Models\\User',
            'users' => 'App\\Models\\User',
            'personal' => 'App\\Models\\Personal',
            'proyecto' => 'App\\Models\\Proyecto',
            'proyectos' => 'App\\Models\\Proyecto',
            'asignacion' => 'App\\Models\\OperacionPersonalAsignado',
            'asignaciones' => 'App\\Models\\OperacionPersonalAsignado',
            'asistencia' => 'App\\Models\\OperacionAsistencia',
            'prestamo' => 'App\\Models\\Prestamo',
            'prestamos' => 'App\\Models\\Prestamo',
            'transaccion' => 'App\\Models\\Transaccion',
            'transacciones' => 'App\\Models\\Transaccion',
            'planilla' => 'App\\Models\\Planilla',
            'planillas' => 'App\\Models\\Planilla',
        ];

        return $models[strtolower($alias)] ?? null;
    }
}
