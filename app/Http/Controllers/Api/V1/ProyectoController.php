<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProyectoRequest;
use App\Http\Requests\UpdateProyectoRequest;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ProyectoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-proyectos', only: ['index', 'show']),
            new Middleware('permission:create-proyectos', only: ['store']),
            new Middleware('permission:edit-proyectos', only: ['update']),
            new Middleware('permission:delete-proyectos', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Proyecto::with([
            'tipoProyecto',
            'ubicacion.departamentoGeografico',
            'ubicacion.municipio',
            'facturacion.tipoDocumentoFacturacion',
            'facturacion.periodicidadPago'
        ]);

        if ($request->has('estado')) {
            $query->where('estado_proyecto', $request->input('estado'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre_proyecto', 'ilike', "%{$search}%")
                  ->orWhere('correlativo', 'ilike', "%{$search}%")
                  ->orWhere('empresa_cliente', 'ilike', "%{$search}%");
            });
        }

        $proyectos = $query->latest()->paginate(15);

        return response()->json($proyectos);
    }

    public function store(StoreProyectoRequest $request): JsonResponse
    {
        $proyecto = DB::transaction(function () use ($request) {
            $proyecto = Proyecto::create($request->validated());

            if ($request->has('ubicacion')) {
                $proyecto->ubicacion()->create($request->input('ubicacion'));
            }

            if ($request->has('facturacion')) {
                $proyecto->facturacion()->create($request->input('facturacion'));
            }

            return $proyecto;
        });
        
        $proyecto->refresh();
        $proyecto->load([
            'tipoProyecto',
            'ubicacion.departamentoGeografico',
            'ubicacion.municipio',
            'facturacion.tipoDocumentoFacturacion',
            'facturacion.periodicidadPago'
        ]);
        
        return response()->json([
            'message' => 'Proyecto creado exitosamente',
            'data' => $proyecto
        ], 201);
    }

    public function show(Proyecto $proyecto): JsonResponse
    {
        $proyecto->load([
            'tipoProyecto',
            'ubicacion.departamentoGeografico',
            'ubicacion.municipio',
            'facturacion.tipoDocumentoFacturacion',
            'facturacion.periodicidadPago'
        ]);
        return response()->json($proyecto);
    }

    public function update(UpdateProyectoRequest $request, Proyecto $proyecto): JsonResponse
    {
        DB::transaction(function () use ($request, $proyecto) {
            $proyecto->update($request->validated());

            if ($request->has('ubicacion')) {
                $proyecto->ubicacion()->updateOrCreate(
                    ['proyecto_id' => $proyecto->id],
                    $request->input('ubicacion')
                );
            }

            if ($request->has('facturacion')) {
                $proyecto->facturacion()->updateOrCreate(
                    ['proyecto_id' => $proyecto->id],
                    $request->input('facturacion')
                );
            }
        });
        
        $proyecto->refresh();
        $proyecto->load([
            'tipoProyecto',
            'ubicacion.departamentoGeografico',
            'ubicacion.municipio',
            'facturacion.tipoDocumentoFacturacion',
            'facturacion.periodicidadPago'
        ]);

        return response()->json([
            'message' => 'Proyecto actualizado exitosamente',
            'data' => $proyecto
        ]);
    }

    public function destroy(Proyecto $proyecto): JsonResponse
    {
        $proyecto->delete();

        return response()->json([
            'message' => 'Proyecto eliminado exitosamente'
        ]);
    }
}
