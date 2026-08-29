<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BodegaMovimiento;
use App\Services\BodegaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class BodegaMovimientoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-bodega', only: ['index', 'show']),
            new Middleware('permission:manage-bodega', only: ['store', 'entrega']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = BodegaMovimiento::with([
            'variante.producto.categoria',
            'personal:id,nombres,apellidos',
            'proyecto:id,nombre_proyecto',
            'proveedor:id,codigo,nombre',
            'facturaCompra:id,serie,numero_factura,fecha_factura',
            'registradoPor:id,name',
        ]);

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('variante_id')) {
            $query->where('variante_id', $request->variante_id);
        }
        if ($request->filled('producto_id')) {
            $query->whereHas('variante', fn ($q) => $q->where('producto_id', $request->producto_id));
        }
        if ($request->filled('categoria_id')) {
            $query->whereHas('variante.producto', fn ($q) => $q->where('categoria_id', $request->categoria_id));
        }
        if ($request->filled('personal_id')) {
            $query->where('personal_id', $request->personal_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_movimiento', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_movimiento', '<=', $request->fecha_hasta);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('variante.producto', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%"));
        }

        $items = $query->orderByDesc('fecha_movimiento')->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function show(int $id): JsonResponse
    {
        $mov = BodegaMovimiento::with([
            'variante.producto.categoria',
            'personal',
            'proyecto',
            'proveedor',
            'facturaCompra',
            'registradoPor:id,name',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $mov]);
    }

    public function store(Request $request, BodegaService $bodegaService): JsonResponse
    {
        $data = $request->validate([
            'variante_id' => ['required', 'exists:bodega_variantes,id'],
            'tipo' => ['required', 'in:ingreso,egreso,ajuste,ajuste_inicial,merma'],
            'cantidad' => ['required_unless:tipo,ajuste', 'integer', 'min:1'],
            'existencia_nueva' => ['required_if:tipo,ajuste', 'integer', 'min:0'],
            'fecha_movimiento' => ['nullable', 'date'],
            'personal_id' => ['nullable', 'exists:personal,id'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'proveedor_id' => ['nullable', 'exists:bodega_proveedores,id'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'observaciones' => ['nullable', 'string'],
        ]);

        try {
            if (($data['tipo'] ?? '') === 'ingreso') {
                return response()->json([
                    'success' => false,
                    'message' => 'Los ingresos se registran con una factura de compra en Bodega → Compras.',
                ], 422);
            }

            $payload = array_merge($data, [
                'registrado_por_user_id' => Auth::id(),
                'cantidad' => $data['cantidad'] ?? 1,
            ]);

            $mov = $bodegaService->registrarMovimiento($payload);
            $mov->load(['variante.producto.categoria', 'personal:id,nombres,apellidos', 'proveedor']);

            return response()->json([
                'success' => true,
                'message' => 'Movimiento registrado.',
                'data' => $mov,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function entrega(Request $request, BodegaService $bodegaService): JsonResponse
    {
        $data = $request->validate([
            'variante_id' => ['required', 'exists:bodega_variantes,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'personal_id' => ['required', 'exists:personal,id'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'fecha_movimiento' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'observaciones' => ['nullable', 'string'],
        ]);

        try {
            $mov = $bodegaService->entregarAPersonal(array_merge($data, [
                'registrado_por_user_id' => Auth::id(),
            ]));
            $mov->load(['variante.producto.categoria', 'personal:id,nombres,apellidos', 'proyecto:id,nombre_proyecto']);

            $esUniforme = (bool) ($mov->variante->producto->es_uniforme ?? false);

            return response()->json([
                'success' => true,
                'message' => 'Entrega registrada.',
                'data' => $mov,
                'es_uniforme' => $esUniforme,
                'sugerir_descuento_uniforme' => $esUniforme,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
