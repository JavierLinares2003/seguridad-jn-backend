<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Catalogos\Departamento;
use App\Models\Catalogos\DepartamentoGeografico;
use App\Models\Catalogos\EstadoCivil;
use App\Models\Catalogos\Municipio;
use App\Models\Catalogos\NivelEstudio;
use App\Models\Catalogos\Parentesco;
use App\Models\Catalogos\RedSocial;
use App\Models\Catalogos\Sexo;
use App\Models\Catalogos\TipoContratacion;
use App\Models\Catalogos\TipoDocumentoPersonal;
use App\Models\Catalogos\TipoDocumentoProyecto;
use App\Models\Catalogos\TipoPago;
use App\Models\Catalogos\TipoPersonal;
use App\Models\Catalogos\TipoProyecto;
use App\Models\Catalogos\TipoSangre;
use App\Models\Catalogos\Turno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * Mapping of catalog names to their models.
     */
    protected array $catalogos = [
        'estados-civiles' => EstadoCivil::class,
        'tipos-sangre' => TipoSangre::class,
        'sexos' => Sexo::class,
        'tipos-contratacion' => TipoContratacion::class,
        'tipos-pago' => TipoPago::class,
        'departamentos' => Departamento::class,
        'departamentos-geograficos' => DepartamentoGeografico::class,
        'municipios' => Municipio::class,
        'parentescos' => Parentesco::class,
        'redes-sociales' => RedSocial::class,
        'niveles-estudio' => NivelEstudio::class,
        'tipos-personal' => TipoPersonal::class,
        'turnos' => Turno::class,
        'tipos-proyecto' => TipoProyecto::class,
        'tipos-documentos-personal' => TipoDocumentoPersonal::class,

        'tipos-documentos-proyecto' => TipoDocumentoProyecto::class,
        'periodicidades-pago' => \App\Models\Catalogos\PeriodicidadPago::class,
        'tipos-documentos-facturacion' => \App\Models\Catalogos\TipoDocumentoFacturacion::class,
    ];

    /**
     * Get all available catalogs.
     *
     * GET /api/v1/catalogos
     */
    public function catalogos(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => array_keys($this->catalogos),
        ]);
    }

    /**
     * Get all items from a specific catalog.
     *
     * GET /api/v1/catalogos/{catalogo}
     */
    public function index(string $catalogo, Request $request): JsonResponse
    {


        if (!isset($this->catalogos[$catalogo])) {
            return response()->json([
                'success' => false,
                'message' => "Catálogo '{$catalogo}' no encontrado.",
                'error' => 'catalog_not_found',
            ], 404);
        }

        $model = $this->catalogos[$catalogo];
        $query = $model::query();

        // Filter by activo (default: only activos)
        if ($request->has('todos')) {
            // Return all records
        } elseif ($request->has('inactivos')) {
            $query->where('activo', false);
        } else {
            $query->where('activo', true);
        }

        // Special case for niveles_estudio: order by 'orden'
        if ($catalogo === 'niveles-estudio') {
            $query->orderBy('orden');
        } else {
            $query->orderBy('nombre');
        }

        // Special case for municipios: filter by departamento
        if ($catalogo === 'municipios' && $request->has('departamento_id')) {
            $query->where('departamento_geo_id', $request->departamento_id);
        }

        // Include relationships
        if ($catalogo === 'municipios') {
            $query->with('departamentoGeografico:id,codigo,nombre');
        }

        if ($catalogo === 'departamentos-geograficos' && $request->has('con_municipios')) {
            $query->with(['municipios' => fn($q) => $q->where('activo', true)->orderBy('nombre')]);
        }

        $data = $query->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * Get a specific item from a catalog.
     *
     * GET /api/v1/catalogos/{catalogo}/{id}
     */
    public function show(string $catalogo, int $id): JsonResponse
    {
        if (!isset($this->catalogos[$catalogo])) {
            return response()->json([
                'success' => false,
                'message' => "Catálogo '{$catalogo}' no encontrado.",
                'error' => 'catalog_not_found',
            ], 404);
        }

        $model = $this->catalogos[$catalogo];
        $item = $model::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        // Load relationships
        if ($catalogo === 'municipios') {
            $item->load('departamentoGeografico:id,codigo,nombre');
        }

        if ($catalogo === 'departamentos-geograficos') {
            $item->load(['municipios' => fn($q) => $q->where('activo', true)->orderBy('nombre')]);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * Get all catalogs data in a single request.
     * Useful for initial app load.
     *
     * GET /api/v1/catalogos/all
     */
    public function all(): JsonResponse
    {
        $data = [];

        foreach ($this->catalogos as $key => $model) {
            $query = $model::where('activo', true);

            if ($key === 'niveles-estudio') {
                $query->orderBy('orden');
            } else {
                $query->orderBy('nombre');
            }

            // Exclude municipios from bulk load (too many records)
            if ($key === 'municipios') {
                continue;
            }

            $data[$key] = $query->get();
        }



        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
