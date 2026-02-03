<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoConfiguracionPersonal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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
        return response()->json($proyecto->configuracionPersonal()->with('tipoPersonal')->get());
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
        return response()->json($config, 201);
    }

    public function update(Request $request, Proyecto $proyecto, ProyectoConfiguracionPersonal $configuracion): JsonResponse
    {
        if ($configuracion->proyecto_id !== $proyecto->id) {
            abort(404);
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

        $configuracion->update($validated);
        return response()->json($configuracion);
    }

    public function destroy(Proyecto $proyecto, ProyectoConfiguracionPersonal $configuracion): JsonResponse
    {
        if ($configuracion->proyecto_id !== $proyecto->id) {
            abort(404);
        }
        
        $configuracion->delete();
        return response()->json(null, 204);
    }
}
