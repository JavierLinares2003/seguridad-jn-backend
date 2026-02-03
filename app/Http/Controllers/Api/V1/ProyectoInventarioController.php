<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Models\ProyectoInventario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProyectoInventarioController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-proyectos', only: ['index', 'show']),
            new Middleware('permission:manage-proyectos-inventario', only: ['store', 'update', 'destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Proyecto $proyecto): JsonResponse
    {
        return response()->json($proyecto->inventario);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $validated = $request->validate([
            'codigo_inventario' => [
                'required', 
                'string', 
                'max:50',
                Rule::unique('proyectos_inventario')->where(fn ($query) => $query->where('proyecto_id', $proyecto->id))
            ],
            'nombre_item' => 'required|string|max:200',
            'cantidad_asignada' => 'required|integer|min:1',
            'estado_item' => 'required|string|in:asignado,en_uso,devuelto,dañado',
            'fecha_asignacion' => 'nullable|date',
            'fecha_devolucion' => 'nullable|date|after_or_equal:fecha_asignacion',
            'observaciones' => 'nullable|string',
        ]);

        // Default fecha_asignacion if not provided is handled by DB default, but Eloquent doesn't read DB defaults automatically unless defined in model or set manually.
        if (!isset($validated['fecha_asignacion'])) {
            $validated['fecha_asignacion'] = now();
        }

        $item = $proyecto->inventario()->create($validated);
        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Proyecto $proyecto, ProyectoInventario $inventario): JsonResponse
    {
         if ($inventario->proyecto_id !== $proyecto->id) {
            abort(404);
        }
        return response()->json($inventario);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Proyecto $proyecto, ProyectoInventario $inventario): JsonResponse
    {
         if ($inventario->proyecto_id !== $proyecto->id) {
            abort(404);
        }

        $validated = $request->validate([
            'codigo_inventario' => [
                'sometimes',
                'required', 
                'string', 
                'max:50',
                Rule::unique('proyectos_inventario')->ignore($inventario->id)->where(fn ($query) => $query->where('proyecto_id', $proyecto->id))
            ],
            'nombre_item' => 'sometimes|required|string|max:200',
            'cantidad_asignada' => 'sometimes|required|integer|min:1',
            'estado_item' => 'sometimes|required|string|in:asignado,en_uso,devuelto,dañado',
            'fecha_asignacion' => 'nullable|date',
            'fecha_devolucion' => 'nullable|date|after_or_equal:fecha_asignacion',
            'observaciones' => 'nullable|string',
        ]);

        $inventario->update($validated);
        return response()->json($inventario);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Proyecto $proyecto, ProyectoInventario $inventario): JsonResponse
    {
         if ($inventario->proyecto_id !== $proyecto->id) {
            abort(404);
        }
        
        $inventario->delete();
        return response()->json(null, 204);
    }
}
