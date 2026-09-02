<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BodegaCategoria;
use App\Models\BodegaCompra;
use App\Models\BodegaEntrega;
use App\Models\BodegaFacturaCompra;
use App\Models\BodegaKit;
use App\Models\BodegaMovimiento;
use App\Models\BodegaProducto;
use App\Models\BodegaProveedor;
use App\Models\BodegaVariante;
use App\Services\BodegaFacturaPdfService;
use App\Services\BodegaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BodegaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-bodega', only: [
                'dashboard', 'categorias', 'proveedores', 'stockBajo', 'entregas', 'showEntrega', 'boletaEntrega',
                'kits', 'showKit', 'facturasCompra', 'showFacturaCompra',
                'solicitudesCompra', 'showSolicitudCompra',
            ]),
            new Middleware('permission:manage-bodega', only: [
                'storeCategoria', 'updateCategoria', 'destroyCategoria', 'storeProveedor', 'updateProveedor', 'storeEntrega',
                'devolverEntrega',
                'storeKit', 'updateKit', 'destroyKit',
                'storeFacturaCompra', 'leerFacturaPdf',
                'storeSolicitudCompra', 'avanzarSolicitudCompra',
            ]),
        ];
    }

    public function dashboard(): JsonResponse
    {
        $categorias = BodegaCategoria::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->withCount(['productos as productos_count' => fn ($q) => $q->where('activo', true)])
            ->get()
            ->map(function (BodegaCategoria $cat) {
                $existencia = BodegaVariante::query()
                    ->whereHas('producto', fn ($q) => $q->where('categoria_id', $cat->id)->where('activo', true))
                    ->where('activo', true)
                    ->sum('existencia');

                $bajo = BodegaVariante::query()
                    ->whereHas('producto', fn ($q) => $q->where('categoria_id', $cat->id)->where('activo', true))
                    ->where('activo', true)
                    ->whereColumn('existencia', '<=', 'stock_minimo')
                    ->count();

                return [
                    'id' => $cat->id,
                    'nombre' => $cat->nombre,
                    'codigo' => $cat->codigo,
                    'icono' => $cat->icono,
                    'prefijo_correlativo' => $cat->prefijo_correlativo,
                    'productos_count' => $cat->productos_count,
                    'existencia_total' => (int) $existencia,
                    'stock_bajo_count' => $bajo,
                ];
            });

        $ultimos = BodegaMovimiento::with([
            'variante.producto.categoria',
            'personal:id,nombres,apellidos',
            'registradoPor:id,name',
        ])
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $entregasRecientes = BodegaMovimiento::with([
            'variante.producto',
            'personal:id,nombres,apellidos',
        ])
            ->where('tipo', 'egreso')
            ->whereNotNull('personal_id')
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $totales = [
            'productos' => BodegaProducto::where('activo', true)->count(),
            'variantes' => BodegaVariante::where('activo', true)->count(),
            'existencia' => (int) BodegaVariante::where('activo', true)->sum('existencia'),
            'stock_bajo' => BodegaVariante::where('activo', true)->whereColumn('existencia', '<=', 'stock_minimo')->count(),
            'movimientos_hoy' => BodegaMovimiento::whereDate('fecha_movimiento', today())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'categorias' => $categorias,
                'totales' => $totales,
                'ultimos_movimientos' => $ultimos,
                'entregas_recientes' => $entregasRecientes,
            ],
        ]);
    }

    public function categorias(): JsonResponse
    {
        $items = BodegaCategoria::orderBy('orden')->orderBy('nombre')->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeCategoria(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'codigo' => ['nullable', 'string', 'max:50', 'unique:bodega_categorias,codigo'],
            'prefijo_correlativo' => ['nullable', 'string', 'max:5'],
            'icono' => ['nullable', 'string', 'max:50'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        if (empty($data['codigo'])) {
            $base = \Illuminate\Support\Str::slug($data['nombre'], '_');
            $codigo = $base ?: 'categoria';
            $i = 2;
            while (BodegaCategoria::where('codigo', $codigo)->exists()) {
                $codigo = $base . '_' . $i;
                $i++;
            }
            $data['codigo'] = $codigo;
        }

        if (!empty($data['prefijo_correlativo'])) {
            $data['prefijo_correlativo'] = strtoupper($data['prefijo_correlativo']);
        }

        $item = BodegaCategoria::create($data);

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function updateCategoria(Request $request, int $id): JsonResponse
    {
        $item = BodegaCategoria::findOrFail($id);
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:120'],
            'codigo' => ['sometimes', 'string', 'max:50', 'unique:bodega_categorias,codigo,' . $id],
            'prefijo_correlativo' => ['nullable', 'string', 'max:5'],
            'icono' => ['nullable', 'string', 'max:50'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);
        if (!empty($data['prefijo_correlativo'])) {
            $data['prefijo_correlativo'] = strtoupper($data['prefijo_correlativo']);
        }
        $item->update($data);

        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function destroyCategoria(int $id): JsonResponse
    {
        $item = BodegaCategoria::findOrFail($id);
        $productos = $item->productos()->count();

        if ($productos > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: tiene {$productos} producto(s). Muévalos o elimínelos primero.",
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada.',
        ]);
    }

    public function proveedores(Request $request): JsonResponse
    {
        $query = BodegaProveedor::query()->orderBy('nombre');
        if (!$request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('codigo', 'ilike', "%{$search}%")
                    ->orWhere('insumo', 'ilike', "%{$search}%")
                    ->orWhere('telefono', 'ilike', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function storeProveedor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'codigo' => ['nullable', 'string', 'max:20', 'unique:bodega_proveedores,codigo'],
            'insumo' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'numero_cuenta' => ['nullable', 'string', 'max:80'],
            'banco' => ['nullable', 'string', 'max:80'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],
            'activo' => ['nullable', 'boolean'],
        ]);

        if (empty($data['codigo'])) {
            unset($data['codigo']);
        }
        $item = BodegaProveedor::create($data);
        app(BodegaService::class)->asegurarCodigoProveedor($item);

        return response()->json(['success' => true, 'data' => $item->fresh()], 201);
    }

    public function updateProveedor(Request $request, int $id): JsonResponse
    {
        $item = BodegaProveedor::findOrFail($id);
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:200'],
            'codigo' => ['nullable', 'string', 'max:20', 'unique:bodega_proveedores,codigo,' . $item->id],
            'insumo' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'numero_cuenta' => ['nullable', 'string', 'max:80'],
            'banco' => ['nullable', 'string', 'max:80'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $item->update($data);

        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function stockBajo(): JsonResponse
    {
        $items = BodegaVariante::with(['producto.categoria'])
            ->where('activo', true)
            ->whereColumn('existencia', '<=', 'stock_minimo')
            ->orderBy('existencia')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function entregas(Request $request): JsonResponse
    {
        $query = BodegaEntrega::with([
            'personal:id,nombres,apellidos',
            'personalOperaciones:id,nombres,apellidos',
            'items.variante.producto.categoria',
            'registradoPor:id,name',
        ]);

        if ($request->filled('personal_id')) {
            $query->where('personal_id', $request->personal_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_entrega', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_entrega', '<=', $request->fecha_hasta);
        }

        $items = $query->orderByDesc('fecha_entrega')->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function showEntrega(int $id): JsonResponse
    {
        $entrega = BodegaEntrega::with([
            'personal:id,nombres,apellidos,dpi,puesto,estado',
            'personalOperaciones:id,nombres,apellidos,dpi,puesto',
            'items.variante.producto.categoria',
            'movimientos.variante.producto',
            'registradoPor:id,name',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $entrega]);
    }

    public function boletaEntrega(int $id)
    {
        $entrega = BodegaEntrega::with([
            'personal:id,nombres,apellidos,dpi,puesto,estado',
            'personalOperaciones:id,nombres,apellidos,dpi,puesto',
            'items.variante.producto',
            'registradoPor:id,name',
        ])->findOrFail($id);

        if (!$entrega->numero_boleta) {
            $entrega->refresh();
        }

        $condiciones = $entrega->items
            ->map(fn ($it) => $it->variante?->condicion)
            ->filter()
            ->unique()
            ->values();

        $control = strtolower((string) request('control', ''));
        $esSalida = $control === 'entrada' ? false : ($control === 'salida' ? true : !$entrega->devuelta_at);

        $logo = public_path('images/imagen-removebg-preview.png');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.bodega.boleta', [
            'entrega' => $entrega,
            'personal' => $entrega->personal,
            'esNuevo' => $condiciones->isEmpty() || $condiciones->contains('nuevo'),
            'esUsado' => $condiciones->contains('usado'),
            'esSalida' => $esSalida,
            'logoPath' => is_file($logo) ? $logo : null,
        ]);
        $pdf->setPaper('letter', 'portrait');

        $numero = $entrega->numero_boleta ?: str_pad((string) $entrega->id, 7, '0', STR_PAD_LEFT);

        return $pdf->stream('BOLETA-BODEGA-' . $numero . '.pdf');
    }

    public function storeEntrega(Request $request, BodegaService $bodegaService): JsonResponse
    {
        $data = $request->validate([
            'personal_id' => ['required', 'exists:personal,id'],
            'personal_operaciones_id' => ['nullable', 'exists:personal,id'],
            'tipo' => ['required', 'in:simple,kit,reposicion'],
            'cobrar' => ['required', 'boolean'],
            'motivo_reposicion' => ['nullable', 'string', 'max:80'],
            'cambio_por_dano' => ['nullable', 'boolean'],
            'variante_entrada_dano_id' => ['nullable', 'exists:bodega_variantes,id'],
            'cantidad_entrada_dano' => ['nullable', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string'],
            'fecha_entrega' => ['nullable', 'date'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variante_id' => ['required', 'exists:bodega_variantes,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'descuento' => ['nullable', 'array'],
            'descuento.cuotas_totales' => ['nullable', 'integer', 'min:1', 'max:60'],
            'descuento.fecha_inicio' => ['nullable', 'date'],
            'descuento.descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $resultado = $bodegaService->crearEntregaCompleta(array_merge($data, [
                'registrado_por_user_id' => Auth::id(),
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Entrega registrada.',
                'data' => $resultado['entrega'],
                'grupo_uniforme' => $resultado['grupo_uniforme'],
                'sugerir_descuento_uniforme' => $resultado['sugerir_descuento_uniforme'],
                'monto_total' => (float) $resultado['entrega']->monto_total,
                'cobrar' => (bool) $resultado['entrega']->cobrar,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function devolverEntrega(Request $request, int $id, BodegaService $bodegaService): JsonResponse
    {
        $entrega = BodegaEntrega::findOrFail($id);
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'integer', 'exists:bodega_entrega_items,id'],
            'items.*.cantidad' => ['required_with:items', 'integer', 'min:1'],
        ]);

        try {
            $actualizada = $bodegaService->registrarDevolucion(
                $entrega,
                $data['items'] ?? [],
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => $actualizada->devuelta_at
                    ? 'Boleta cerrada: se devolvió todo el equipo.'
                    : 'Devolución parcial registrada. La boleta sigue pendiente.',
                'data' => $actualizada,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function kits(Request $request): JsonResponse
    {
        $query = BodegaKit::with(['items.producto.variantes' => fn ($q) => $q->where('activo', true)])
            ->orderBy('nombre');

        if (!$request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }

        $kits = $query->get();
        $kits->each(function (BodegaKit $kit) {
            foreach ($kit->items as $item) {
                $item->producto?->attachProductoToVariantes();
            }
        });

        return response()->json(['success' => true, 'data' => $kits]);
    }

    public function showKit(int $id): JsonResponse
    {
        $kit = BodegaKit::with(['items.producto.variantes' => fn ($q) => $q->where('activo', true)])
            ->findOrFail($id);
        foreach ($kit->items as $item) {
            $item->producto?->attachProductoToVariantes();
        }

        return response()->json(['success' => true, 'data' => $kit]);
    }

    public function storeKit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:bodega_productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $kit = DB::transaction(function () use ($data) {
            $kit = BodegaKit::create([
                'nombre' => $data['nombre'],
                'precio' => $data['precio'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'activo' => true,
            ]);
            foreach ($data['items'] as $item) {
                $kit->items()->create([
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                ]);
            }
            app(BodegaService::class)->asegurarCodigoKit($kit);

            return $kit->load(['items.producto']);
        });

        return response()->json(['success' => true, 'data' => $kit], 201);
    }

    public function updateKit(Request $request, int $id): JsonResponse
    {
        $kit = BodegaKit::findOrFail($id);
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:150'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.producto_id' => ['required_with:items', 'exists:bodega_productos,id'],
            'items.*.cantidad' => ['required_with:items', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($kit, $data) {
            $kit->update(collect($data)->except('items')->all());
            app(BodegaService::class)->asegurarCodigoKit($kit);
            if (isset($data['items'])) {
                $kit->items()->delete();
                foreach ($data['items'] as $item) {
                    $kit->items()->create([
                        'producto_id' => $item['producto_id'],
                        'cantidad' => $item['cantidad'],
                    ]);
                }
            }
        });

        return response()->json(['success' => true, 'data' => $kit->fresh(['items.producto'])]);
    }

    public function destroyKit(int $id): JsonResponse
    {
        $kit = BodegaKit::findOrFail($id);
        $kit->update(['activo' => false]);

        return response()->json(['success' => true, 'message' => 'Combo desactivado.']);
    }

    public function facturasCompra(Request $request): JsonResponse
    {
        $query = BodegaFacturaCompra::with([
            'proveedor:id,codigo,nombre',
            'items.variante.producto:id,codigo,nombre',
            'registradoPor:id,name',
        ])->withCount('items');

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_factura', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_factura', '<=', $request->fecha_hasta);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'ilike', "%{$search}%")
                    ->orWhere('numero_factura', 'ilike', "%{$search}%")
                    ->orWhereHas('proveedor', function ($p) use ($search) {
                        $p->where('codigo', 'ilike', "%{$search}%")
                            ->orWhere('nombre', 'ilike', "%{$search}%");
                    });
            });
        }

        $items = $query->orderByDesc('fecha_factura')->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function showFacturaCompra(int $id): JsonResponse
    {
        $factura = BodegaFacturaCompra::with([
            'proveedor',
            'items.variante.producto.categoria',
            'registradoPor:id,name',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $factura]);
    }

    public function storeFacturaCompra(Request $request, BodegaService $bodegaService): JsonResponse
    {
        $data = $request->validate([
            'proveedor_id' => ['required', 'exists:bodega_proveedores,id'],
            'fecha_factura' => ['required', 'date'],
            'serie' => ['nullable', 'string', 'max:40'],
            'numero_factura' => ['required', 'string', 'max:60'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variante_id' => ['required', 'exists:bodega_variantes,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $factura = $bodegaService->registrarFacturaCompra(array_merge($data, [
                'registrado_por_user_id' => Auth::id(),
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Factura de compra registrada. El inventario se cargó con las líneas.',
                'data' => $factura,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function leerFacturaPdf(Request $request, BodegaFacturaPdfService $pdfService): JsonResponse
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        try {
            $archivo = $request->file('pdf');
            $path = $archivo->getRealPath() ?: $archivo->getPathname();
            $datos = $pdfService->extraer($path);

            return response()->json([
                'success' => true,
                'message' => 'Datos leídos del PDF. Revisa y completa lo que falte antes de grabar.',
                'data' => $datos,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function solicitudesCompra(Request $request): JsonResponse
    {
        $query = BodegaCompra::with(['proveedor:id,codigo,nombre', 'items', 'registradoPor:id,name']);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        $items = $query->orderByDesc('id')->paginate($request->integer('per_page', 20));
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function showSolicitudCompra(int $id): JsonResponse
    {
        $compra = BodegaCompra::with(['proveedor', 'items', 'factura', 'registradoPor:id,name', 'aprobadoPor:id,name'])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $compra]);
    }

    public function storeSolicitudCompra(Request $request): JsonResponse
    {
        $data = $request->validate([
            'proveedor_id' => ['nullable', 'exists:bodega_proveedores,id'],
            'observaciones' => ['nullable', 'string'],
            'total_estimado' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:200'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_estimado' => ['nullable', 'numeric', 'min:0'],
        ]);

        $compra = DB::transaction(function () use ($data) {
            $ultimo = BodegaCompra::query()->orderByDesc('id')->lockForUpdate()->first();
            $seq = $ultimo ? ((int) preg_replace('/\D/', '', (string) $ultimo->codigo) + 1) : 1;
            $compra = BodegaCompra::create([
                'codigo' => 'SC-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'estado' => 'solicitud',
                'fecha_solicitud' => now()->toDateString(),
                'total_estimado' => $data['total_estimado'] ?? collect($data['items'])->sum(fn ($i) => ($i['cantidad'] ?? 1) * ($i['precio_estimado'] ?? 0)),
                'observaciones' => $data['observaciones'] ?? null,
                'registrado_por_user_id' => Auth::id(),
            ]);
            foreach ($data['items'] as $item) {
                $compra->items()->create([
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_estimado' => $item['precio_estimado'] ?? 0,
                ]);
            }
            return $compra;
        });

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de compra creada.',
            'data' => $compra->load(['proveedor', 'items']),
        ], 201);
    }

    public function avanzarSolicitudCompra(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'accion' => ['required', 'in:cotizacion,aprobar,anticipo,recibir,saldo,cancelar'],
        ]);

        $compra = BodegaCompra::findOrFail($id);
        if ($compra->estado === 'cancelada') {
            return response()->json(['success' => false, 'message' => 'La solicitud está cancelada.'], 422);
        }

        $hoy = now()->toDateString();
        match ($data['accion']) {
            'cotizacion' => $compra->update([
                'estado' => 'cotizacion',
                'fecha_cotizacion' => $compra->fecha_cotizacion ?: $hoy,
            ]),
            'aprobar' => $compra->update([
                'estado' => 'aprobada',
                'fecha_aprobacion' => $compra->fecha_aprobacion ?: $hoy,
                'aprobado_por_user_id' => Auth::id(),
            ]),
            'anticipo' => $compra->update([
                'estado' => 'anticipo_pagado',
                'anticipo_pagado' => true,
                'fecha_anticipo_pagado' => $compra->fecha_anticipo_pagado ?: $hoy,
            ]),
            'recibir' => $compra->update([
                'estado' => 'recibida',
                'fecha_recepcion' => $compra->fecha_recepcion ?: $hoy,
            ]),
            'saldo' => $compra->update([
                'estado' => 'saldo_pagado',
                'saldo_pagado' => true,
                'fecha_saldo_pagado' => $compra->fecha_saldo_pagado ?: $hoy,
            ]),
            'cancelar' => $compra->update(['estado' => 'cancelada']),
        };

        return response()->json([
            'success' => true,
            'message' => 'Paso marcado. El pago queda apuntado como ya se pagó; no hay conciliación bancaria.',
            'data' => $compra->fresh(['proveedor', 'items']),
        ]);
    }
}
