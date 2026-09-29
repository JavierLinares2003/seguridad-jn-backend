<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OperacionPersonalAsignado;
use App\Models\Proyecto;
use App\Models\ProyectoConfiguracionPersonal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProyectoConfiguracionPersonalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-proyectos', only: ['index', 'show']),
            new Middleware('permission:manage-proyectos-configuracion', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Proyecto $proyecto): JsonResponse
    {
        return response()->json($proyecto->configuracionPersonal()->with(['tipoPersonal', 'turno'])->get());
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $validated = $request->validate([
            'nombre_puesto' => 'nullable|string|max:100', // Now optional
            'cantidad_requerida' => 'required|integer|min:1',
            'edad_minima' => 'required|integer|min:18',
            'edad_maxima' => 'required|integer|gte:edad_minima',
            'sexo_id' => 'nullable|exists:sexos,id',
            'altura_minima' => 'nullable|numeric|min:0',
            'estudio_minimo_id' => 'nullable|exists:niveles_estudio,id',
            'tipo_personal_id' => 'required|exists:tipos_personal,id',
            'turno_id' => 'required|exists:turnos,id',
            'costo_hora_proyecto' => 'required|numeric|min:0',
            // Validation: pago <= costo
            'pago_hora_personal' => 'required|numeric|min:0|lte:costo_hora_proyecto',
            'estado' => 'string|in:activo,inactivo'
        ], [
            'pago_hora_personal.lte' => 'El pago al personal no puede ser mayor al costo cobrado al proyecto.',
            'edad_maxima.gte' => 'La edad máxima debe ser mayor o igual a la mínima.'
        ]);

        $config = $proyecto->configuracionPersonal()->create($validated);
        $this->recalcularMontoTotal($proyecto);
        return response()->json($config, 201);
    }

    public function update(Request $request, Proyecto $proyecto, ProyectoConfiguracionPersonal $configuracionPersonal): JsonResponse
    {
        // Validar que la configuración pertenece al proyecto
        if ($configuracionPersonal->proyecto_id != $proyecto->id) {
            abort(404, 'Configuración no encontrada en este proyecto');
        }

        $validated = $request->validate([
            'nombre_puesto' => 'nullable|string|max:100',
            'cantidad_requerida' => 'sometimes|required|integer|min:1',
            'edad_minima' => 'sometimes|required|integer|min:18',
            'edad_maxima' => 'sometimes|required|integer|gte:edad_minima',
            'sexo_id' => 'nullable|exists:sexos,id',
            'altura_minima' => 'nullable|numeric|min:0',
            'estudio_minimo_id' => 'nullable|exists:niveles_estudio,id',
            'tipo_personal_id' => 'sometimes|required|exists:tipos_personal,id',
            'turno_id' => 'sometimes|required|exists:turnos,id',
            'costo_hora_proyecto' => 'sometimes|required|numeric|min:0',
            'pago_hora_personal' => 'sometimes|required|numeric|min:0|lte:costo_hora_proyecto',
            'estado' => 'string|in:activo,inactivo'
        ]);

        $configuracionPersonal->update($validated);
        $this->recalcularMontoTotal($proyecto);
        return response()->json($configuracionPersonal);
    }

    public function destroy(Proyecto $proyecto, ProyectoConfiguracionPersonal $configuracionPersonal): JsonResponse
    {
        if ($configuracionPersonal->proyecto_id != $proyecto->id) {
            abort(404, 'Configuración no encontrada en este proyecto');
        }

        try {
            $resultado = DB::transaction(function () use ($proyecto, $configuracionPersonal) {
                $asignaciones = OperacionPersonalAsignado::where('configuracion_puesto_id', $configuracionPersonal->id)->get();
                $finalizadas = 0;

                foreach ($asignaciones as $asignacion) {
                    if (in_array($asignacion->estado_asignacion, ['activa', 'suspendida'], true)) {
                        $asignacion->finalizar('Puesto eliminado de la configuración del proyecto');
                        $finalizadas++;
                    }

                    // La FK es NO ACTION: hay que desvincular para poder borrar la plaza
                    // sin perder el historial de la asignación.
                    $asignacion->configuracion_puesto_id = null;
                    $asignacion->save();
                }

                $id = $configuracionPersonal->id;
                $deleted = $configuracionPersonal->delete();
                $this->recalcularMontoTotal($proyecto);

                return [
                    'deleted' => $deleted,
                    'configuracion_id' => $id,
                    'asignaciones_finalizadas' => $finalizadas,
                    'asignaciones_desvinculadas' => $asignaciones->count(),
                ];
            });

            Log::info('Configuración de puesto eliminada', $resultado);

            return response()->json([
                'success' => true,
                'message' => 'Puesto eliminado correctamente. Las asignaciones activas de esa plaza se finalizaron.',
                'data' => $resultado,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar configuración', [
                'configuracion_id' => $configuracionPersonal->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la configuración: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function recalcularMontoTotal(Proyecto $proyecto): void
    {
        $total = $proyecto->configuracionPersonal()
            ->selectRaw('SUM(cantidad_requerida * costo_hora_proyecto) as total')
            ->value('total') ?? 0;

        if ($proyecto->facturacion) {
            $proyecto->facturacion->update(['monto_proyecto_total' => $total]);
            $proyecto->facturacion->recalcularImpuesto();
        }
    }
}
