<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planillas\GenerarPlanillaRequest;
use App\Models\Planilla;
use App\Services\PlanillaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
                $request->observaciones
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
        $query = Planilla::with(['creadoPor', 'aprobadoPor']);

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
            'creadoPor',
            'aprobadoPor'
        ])->findOrFail($id);

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
     * @param int $id
     * @param string $formato
     * @return mixed
     */
    public function export(int $id, string $formato)
    {
        $planilla = Planilla::with([
            'detalles.personal',
            'detalles.proyecto'
        ])->findOrFail($id);

        if ($formato === 'excel') {
            return $this->exportarExcel($planilla);
        } elseif ($formato === 'pdf') {
            return $this->exportarPDF($planilla);
        }

        return response()->json([
            'success' => false,
            'message' => 'Formato no soportado. Use "excel" o "pdf"',
        ], 400);
    }

    /**
     * Exportar a Excel (implementación básica)
     */
    private function exportarExcel($planilla)
    {
        // TODO: Implementar exportación a Excel usando Laravel Excel
        // Por ahora, retornar JSON
        return response()->json([
            'success' => true,
            'message' => 'Exportación a Excel pendiente de implementar',
            'data' => $planilla,
        ]);
    }

    /**
     * Exportar a PDF (implementación básica)
     */
    private function exportarPDF($planilla)
    {
        // TODO: Implementar exportación a PDF usando DomPDF o similar
        // Por ahora, retornar JSON
        return response()->json([
            'success' => true,
            'message' => 'Exportación a PDF pendiente de implementar',
            'data' => $planilla,
        ]);
    }
}
