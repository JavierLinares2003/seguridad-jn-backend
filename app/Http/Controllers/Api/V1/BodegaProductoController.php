<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BodegaProducto;
use App\Models\BodegaVariante;
use App\Services\BodegaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BodegaProductoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-bodega', only: ['index', 'show']),
            new Middleware('permission:manage-bodega', only: ['store', 'update', 'destroy', 'storeVariante', 'updateVariante']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = BodegaProducto::with(['categoria', 'variantes' => fn ($q) => $q->where('activo', true)])
            ->where('activo', true);

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('codigo', 'ilike', "%{$search}%")
                    ->orWhereHas('variantes', fn ($v) => $v->where('sku', 'ilike', "%{$search}%"));
            });
        }
        if ($request->boolean('es_uniforme')) {
            $query->where('es_uniforme', true);
        }
        // Catálogo para armar kit: uniforme + categorías relacionadas
        if ($request->boolean('para_kit')) {
            $query->where(function ($q) {
                $q->where('es_uniforme', true)
                    ->orWhereHas('categoria', function ($c) {
                        $c->whereIn('codigo', [
                            'uniforme_agentes',
                            'uniforme_admin',
                            'sueter_militar',
                            'accesorios_uniforme',
                        ]);
                    });
            });
        }
        if ($request->boolean('stock_bajo')) {
            $query->whereHas('variantes', function ($q) {
                $q->where('activo', true)->whereColumn('existencia', '<=', 'stock_minimo');
            });
        }

        $items = $query->orderBy('nombre')->paginate($request->integer('per_page', 20));
        $items->getCollection()->each(fn (BodegaProducto $p) => $p->attachProductoToVariantes());

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function show(int $id): JsonResponse
    {
        $producto = BodegaProducto::with([
            'categoria',
            'variantes' => fn ($q) => $q->where('activo', true)->orderBy('genero')->orderBy('talla')->orderBy('condicion'),
        ])->findOrFail($id);
        $producto->attachProductoToVariantes();

        $movimientos = \App\Models\BodegaMovimiento::with(['personal:id,nombres,apellidos', 'registradoPor:id,name'])
            ->whereIn('variante_id', $producto->variantes->pluck('id'))
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'producto' => $producto,
                'movimientos' => $movimientos,
            ],
        ]);
    }

    public function store(Request $request, BodegaService $bodegaService): JsonResponse
    {
        $data = $request->validate([
            'categoria_id' => ['required', 'exists:bodega_categorias,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'unidad' => ['nullable', 'string', 'max:40'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'precio_usado' => ['nullable', 'numeric', 'min:0'],
            'usa_talla' => ['nullable', 'boolean'],
            'usa_condicion' => ['nullable', 'boolean'],
            'usa_genero' => ['nullable', 'boolean'],
            'es_uniforme' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string'],
            'variantes' => ['nullable', 'array'],
            'variantes.*.talla' => ['nullable', 'string', 'max:30'],
            'variantes.*.condicion' => ['nullable', 'string', 'in:nuevo,usado'],
            'variantes.*.genero' => ['nullable', 'string', 'in:mujer,hombre,unisex'],
            'variantes.*.sku' => ['nullable', 'string', 'max:80'],
            'variantes.*.stock_minimo' => ['nullable', 'integer', 'min:0'],
            'variantes.*.existencia_inicial' => ['nullable', 'integer', 'min:0'],
        ]);

        $producto = DB::transaction(function () use ($data, $bodegaService, $request) {
            $producto = BodegaProducto::create([
                'categoria_id' => $data['categoria_id'],
                'nombre' => $data['nombre'],
                'unidad' => $data['unidad'] ?? 'unidad',
                'precio_venta' => $data['precio_venta'] ?? null,
                'precio_usado' => $data['precio_usado'] ?? null,
                'usa_talla' => $data['usa_talla'] ?? false,
                'usa_condicion' => $data['usa_condicion'] ?? false,
                'usa_genero' => $data['usa_genero'] ?? false,
                'es_uniforme' => $data['es_uniforme'] ?? false,
                'observaciones' => $data['observaciones'] ?? null,
                'activo' => true,
            ]);
            $producto->refresh();

            $variantesInput = $data['variantes'] ?? [['talla' => null, 'condicion' => null, 'genero' => null]];
            if (count($variantesInput) === 0) {
                $variantesInput = [[]];
            }
            if (($data['usa_condicion'] ?? false) && count($variantesInput) === 1 && empty($variantesInput[0]['condicion'])) {
                $base = $variantesInput[0];
                $variantesInput = [
                    array_merge($base, ['condicion' => 'nuevo']),
                    array_merge($base, ['condicion' => 'usado', 'existencia_inicial' => 0]),
                ];
            }

            foreach ($variantesInput as $v) {
                $variante = $bodegaService->upsertVariante($producto, $v);
                $inicial = (int) ($v['existencia_inicial'] ?? 0);
                if ($inicial > 0) {
                    $bodegaService->registrarMovimiento([
                        'variante_id' => $variante->id,
                        'tipo' => 'ajuste_inicial',
                        'cantidad' => $inicial,
                        'registrado_por_user_id' => Auth::id(),
                        'observaciones' => 'Existencia inicial al crear producto',
                    ]);
                }
            }

            return $producto->load(['categoria', 'variantes']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Producto creado.',
            'data' => $producto,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $producto = BodegaProducto::findOrFail($id);
        $data = $request->validate([
            'categoria_id' => ['sometimes', 'exists:bodega_categorias,id'],
            'nombre' => ['sometimes', 'string', 'max:200'],
            'unidad' => ['nullable', 'string', 'max:40'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'precio_usado' => ['nullable', 'numeric', 'min:0'],
            'usa_talla' => ['nullable', 'boolean'],
            'usa_condicion' => ['nullable', 'boolean'],
            'usa_genero' => ['nullable', 'boolean'],
            'es_uniforme' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string'],
        ]);
        $producto->update($data);

        return response()->json([
            'success' => true,
            'data' => $producto->fresh(['categoria', 'variantes']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $producto = BodegaProducto::findOrFail($id);
        $producto->update(['activo' => false]);
        BodegaVariante::where('producto_id', $producto->id)->update(['activo' => false]);

        return response()->json(['success' => true, 'message' => 'Producto desactivado.']);
    }

    public function storeVariante(Request $request, int $id, BodegaService $bodegaService): JsonResponse
    {
        $producto = BodegaProducto::findOrFail($id);
        $data = $request->validate([
            'talla' => ['nullable', 'string', 'max:30'],
            'condicion' => ['nullable', 'string', 'in:nuevo,usado'],
            'genero' => ['nullable', 'string', 'in:mujer,hombre,unisex'],
            'sku' => ['nullable', 'string', 'max:80'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
            'existencia_inicial' => ['nullable', 'integer', 'min:0'],
        ]);

        $variante = $bodegaService->upsertVariante($producto, $data);
        $inicial = (int) ($data['existencia_inicial'] ?? 0);
        if ($inicial > 0 && $variante->existencia === 0) {
            $bodegaService->registrarMovimiento([
                'variante_id' => $variante->id,
                'tipo' => 'ajuste_inicial',
                'cantidad' => $inicial,
                'registrado_por_user_id' => Auth::id(),
            ]);
            $variante->refresh();
        }

        return response()->json(['success' => true, 'data' => $variante], 201);
    }

    public function updateVariante(Request $request, int $productoId, int $varianteId): JsonResponse
    {
        $variante = BodegaVariante::where('producto_id', $productoId)->findOrFail($varianteId);
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:80'],
            'stock_minimo' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $variante->update($data);

        return response()->json(['success' => true, 'data' => $variante->fresh()]);
    }
}
