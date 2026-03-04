<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\PlanillaExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planillas\GenerarPlanillaRequest;
use App\Models\Planilla;
use App\Services\PlanillaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class PlanillaController extends Controller
{
    protected $planillaService;

    public function __construct(PlanillaService $planillaService)
    {
        $this->planillaService = $planillaService;
    }

    /**
     * Generar nueva planilla
     * 
     * @param GenerarPlanillaRequest $request
     * @return JsonResponse
     */
    public function generar(GenerarPlanillaRequest $request): JsonResponse
    {
        try {
            $planilla = $this->planillaService->generarPlanilla(
                $request->periodo_inicio,
                $request->periodo_fin,
                $request->proyecto_id,
                $request->observaciones,
                $request->tipo_calculo,
                $request->departamento_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Planilla generada exitosamente',
                'data' => $planilla,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar planilla: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Listar planillas con filtros y paginación
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Planilla::with(['creadoPor', 'aprobadoPor', 'proyecto', 'departamento']);

        // Filtros
        if ($request->has('estado_planilla')) {
            $query->where('estado_planilla', $request->estado_planilla);
        }

        if ($request->has('periodo_inicio')) {
            $query->where('periodo_inicio', '>=', $request->periodo_inicio);
        }

        if ($request->has('periodo_fin')) {
            $query->where('periodo_fin', '<=', $request->periodo_fin);
        }

        // Filtros por ámbito
        if ($request->has('proyecto_id')) {
            if ($request->proyecto_id === 'null' || $request->proyecto_id === '') {
                $query->whereNull('proyecto_id');
            } else {
                $query->where('proyecto_id', $request->proyecto_id);
            }
        }

        if ($request->has('departamento_id')) {
            if ($request->departamento_id === 'null' || $request->departamento_id === '') {
                $query->whereNull('departamento_id');
            } else {
                $query->where('departamento_id', $request->departamento_id);
            }
        }

        // Ordenar por fecha de creación descendente
        $query->orderBy('created_at', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 15);
        $planillas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $planillas->items(),
            'meta' => [
                'current_page' => $planillas->currentPage(),
                'last_page' => $planillas->lastPage(),
                'per_page' => $planillas->perPage(),
                'total' => $planillas->total(),
            ],
        ]);
    }

    /**
     * Obtener detalle de planilla
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $planilla = Planilla::with([
            'detalles.personal',
            'detalles.proyecto',
            'detalles.planilla', // Necesario para el accessor de transacciones
            'creadoPor',
            'aprobadoPor',
            'proyecto',
            'departamento'
        ])->findOrFail($id);

        // Agregar las transacciones con el usuario que las registró a cada detalle
        $planilla->detalles->each(function ($detalle) use ($planilla) {
            $detalle->transacciones = \App\Models\Transaccion::with('registradoPor:id,name')
                ->where('personal_id', $detalle->personal_id)
                ->whereBetween('fecha_transaccion', [
                    $planilla->periodo_inicio,
                    $planilla->periodo_fin
                ])
                ->where('es_descuento', true)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $planilla,
        ]);
    }

    /**
     * Aprobar planilla
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function aprobar(int $id): JsonResponse
    {
        try {
            $planilla = $this->planillaService->aprobarPlanilla($id);

            return response()->json([
                'success' => true,
                'message' => 'Planilla aprobada exitosamente',
                'data' => $planilla,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar planilla: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Marcar planilla como pagada
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function marcarPagada(int $id): JsonResponse
    {
        try {
            $planilla = $this->planillaService->marcarComoPagada($id);

            return response()->json([
                'success' => true,
                'message' => 'Planilla marcada como pagada',
                'data' => $planilla,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancelar planilla
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        try {
            $planilla = $this->planillaService->cancelarPlanilla(
                $id,
                $request->motivo
            );

            return response()->json([
                'success' => true,
                'message' => 'Planilla cancelada',
                'data' => $planilla,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Exportar planilla a Excel o PDF
     *
     * GET /api/v1/operaciones/planillas/{id}/export/{formato}
     */
    public function export(int $id, string $formato)
    {
        if (!in_array($formato, ['excel', 'pdf'])) {
            return response()->json([
                'success' => false,
                'message' => 'Formato no soportado. Use "excel" o "pdf".',
            ], 400);
        }

        $planilla = Planilla::findOrFail($id);

        if ($formato === 'excel') {
            return $this->exportarExcel($planilla);
        }

        return $this->exportarPDF($planilla);
    }

    private function exportarExcel(Planilla $planilla)
    {
        $nombre = 'planilla_' . $planilla->id . '_' . $planilla->periodo_inicio->format('Y-m') . '.xlsx';

        return Excel::download(new PlanillaExport($planilla), $nombre);
    }

    private function exportarPDF(Planilla $planilla)
    {
        // TODO: Implementar exportación a PDF
        return response()->json([
            'success' => false,
            'message' => 'Exportación a PDF pendiente de implementar.',
        ], 501);
    }
}
