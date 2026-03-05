<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProyectoRequest;
use App\Http\Requests\UpdateProyectoRequest;
use App\Models\OperacionPersonalAsignado;
use App\Models\Proyecto;
use App\Models\ProyectoConfiguracionPersonal;
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
            'facturacion.periodicidadPago',
            'primeraConfiguracion.turno',
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

        $proyectos = $query->latest()->paginate($request->input('per_page', 15));

        // Si se solicitan estadísticas, agregarlas a cada proyecto
        if ($request->boolean('con_estadisticas')) {
            $proyectos->getCollection()->transform(function ($proyecto) {
                $proyecto->estadisticas_personal = $this->getEstadisticasProyecto($proyecto->id);
                return $proyecto;
            });
        }

        return response()->json($proyectos);
    }

    /**
     * Obtiene estadísticas de asignación de personal para un proyecto.
     */
    private function getEstadisticasProyecto(int $proyectoId): array
    {
        $configuraciones = ProyectoConfiguracionPersonal::where('proyecto_id', $proyectoId)
            ->where('estado', 'activo')
            ->get();

        $estadisticas = [];

        foreach ($configuraciones as $config) {
            $asignadosActivos = OperacionPersonalAsignado::query()
                ->where('estado_asignacion', 'activa')
                ->where('configuracion_puesto_id', $config->id)
                ->where('fecha_inicio', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('fecha_fin')
                      ->orWhere('fecha_fin', '>=', now());
                })
                ->count();

            $estadisticas[] = [
                'configuracion_id' => $config->id,
                'nombre_puesto' => $config->nombre_puesto,
                'cantidad_requerida' => $config->cantidad_requerida,
                'cantidad_asignada' => $asignadosActivos,
                'faltantes' => max(0, $config->cantidad_requerida - $asignadosActivos),
                'porcentaje_cubierto' => $config->cantidad_requerida > 0
                    ? round(($asignadosActivos / $config->cantidad_requerida) * 100, 2)
                    : 0,
            ];
        }

        return [
            'puestos' => $estadisticas,
            'resumen' => [
                'total_requerido' => collect($estadisticas)->sum('cantidad_requerida'),
                'total_asignado' => collect($estadisticas)->sum('cantidad_asignada'),
                'total_faltantes' => collect($estadisticas)->sum('faltantes'),
            ],
        ];
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

    public function show(Request $request, Proyecto $proyecto): JsonResponse
    {
        $proyecto->load([
            'tipoProyecto',
            'ubicacion.departamentoGeografico',
            'ubicacion.municipio',
            'facturacion.tipoDocumentoFacturacion',
            'facturacion.periodicidadPago',
            'configuracionPersonal.tipoPersonal',
            'configuracionPersonal.turno',
        ]);

        // Siempre incluir estadísticas en el detalle del proyecto
        $proyecto->estadisticas_personal = $this->getEstadisticasProyecto($proyecto->id);

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
