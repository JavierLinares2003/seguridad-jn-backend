<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class AlertaCoberturaController extends Controller
{
    /**
     * Display a listing of coverage alerts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Validar parámetros
        $request->validate([
            'proyecto_id' => 'nullable|exists:proyectos,id',
            'fecha' => 'nullable|date',
            'severidad' => 'nullable|in:critica,alta,media,baja',
            'tipo_alerta' => 'nullable|in:sin_cobertura,cobertura_parcial,proximo_vencimiento',
        ]);

        // Construir query base desde la vista
        $query = DB::table('vw_alertas_cobertura_proyectos');

        // Aplicar filtros
        if ($request->has('proyecto_id')) {
            $query->where('proyecto_id', $request->proyecto_id);
        }

        if ($request->has('severidad')) {
            $query->where('severidad', $request->severidad);
        }

        if ($request->has('tipo_alerta')) {
            $query->where('tipo_alerta', $request->tipo_alerta);
        }

        // Filtro por fecha (para vencimientos próximos)
        if ($request->has('fecha')) {
            $fecha = $request->fecha;
            $query->where(function ($q) use ($fecha) {
                $q->whereNull('proxima_fecha_vencimiento')
                  ->orWhere('proxima_fecha_vencimiento', '>=', $fecha);
            });
        }

        // Obtener resultados
        $alertas = $query->get();

        // Calcular métricas agregadas
        $meta = [
            'total' => $alertas->count(),
            'criticas' => $alertas->where('severidad', 'critica')->count(),
            'altas' => $alertas->where('severidad', 'alta')->count(),
            'medias' => $alertas->where('severidad', 'media')->count(),
            'bajas' => $alertas->where('severidad', 'baja')->count(),
            'sin_cobertura' => $alertas->where('tipo_alerta', 'sin_cobertura')->count(),
            'cobertura_parcial' => $alertas->where('tipo_alerta', 'cobertura_parcial')->count(),
            'proximo_vencimiento' => $alertas->where('tipo_alerta', 'proximo_vencimiento')->count(),
        ];

        // Agrupar por proyecto para resumen
        $porProyecto = $alertas->groupBy('proyecto_id')->map(function ($alertasProyecto) {
            $primera = $alertasProyecto->first();
            return [
                'proyecto_id' => $primera->proyecto_id,
                'nombre_proyecto' => $primera->nombre_proyecto,
                'proyecto_correlativo' => $primera->proyecto_correlativo,
                'cliente_nombre' => $primera->cliente_nombre,
                'total_alertas' => $alertasProyecto->count(),
                'severidad_maxima' => $this->getSeveridadMaxima($alertasProyecto),
                'puestos_afectados' => $alertasProyecto->pluck('nombre_puesto')->unique()->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $alertas,
            'meta' => $meta,
            'resumen_por_proyecto' => $porProyecto,
        ]);
    }

    /**
     * Determinar la severidad máxima de un grupo de alertas
     *
     * @param  \Illuminate\Support\Collection  $alertas
     * @return string
     */
    private function getSeveridadMaxima($alertas)
    {
        $orden = ['critica' => 1, 'alta' => 2, 'media' => 3, 'baja' => 4];
        
        $severidades = $alertas->pluck('severidad')->unique();
        
        $maxSeveridad = $severidades->min(function ($severidad) use ($orden) {
            return $orden[$severidad] ?? 999;
        });

        return $maxSeveridad;
    }
}
