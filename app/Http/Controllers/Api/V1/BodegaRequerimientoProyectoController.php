<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BodegaDevolucionUniforme;
use App\Models\Proyecto;
use App\Models\ProyectoRequerimientoDevolucion;
use App\Models\ProyectoRequerimientoEquipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BodegaRequerimientoProyectoController extends Controller implements HasMiddleware
{
    public const CATALOGO = [
        ['clave' => 'estufa', 'nombre' => 'Estufa', 'columna' => 'izq'],
        ['clave' => 'linternas', 'nombre' => 'Linternas', 'columna' => 'izq'],
        ['clave' => 'libro_consignas', 'nombre' => 'Libro de consignas', 'columna' => 'izq'],
        ['clave' => 'tablilla', 'nombre' => 'Tablilla', 'columna' => 'izq'],
        ['clave' => 'hojas_control', 'nombre' => 'Hojas de control', 'columna' => 'izq'],
        ['clave' => 'telefono', 'nombre' => 'Teléfono', 'columna' => 'izq'],
        ['clave' => 'cargador_tel', 'nombre' => 'Cargador de teléfono', 'columna' => 'izq'],
        ['clave' => 'radios', 'nombre' => 'Radios', 'columna' => 'izq'],
        ['clave' => 'detector_metal', 'nombre' => 'Detector de metal', 'columna' => 'izq'],
        ['clave' => 'paralayzer', 'nombre' => 'Paralayzer', 'columna' => 'izq'],
        ['clave' => 'batones', 'nombre' => 'Batones y porta batón', 'columna' => 'izq'],
        ['clave' => 'revolver', 'nombre' => 'Revólver', 'columna' => 'der'],
        ['clave' => 'pistola_9mm', 'nombre' => 'Pistola 9mm', 'columna' => 'der'],
        ['clave' => 'funda', 'nombre' => 'Funda', 'columna' => 'der'],
        ['clave' => 'escopeta', 'nombre' => 'Escopeta', 'columna' => 'der'],
        ['clave' => 'porta_escopeta', 'nombre' => 'Porta escopeta', 'columna' => 'der'],
        ['clave' => 'cinturones', 'nombre' => 'Cinturones', 'columna' => 'der'],
        ['clave' => 'municiones', 'nombre' => 'Municiones', 'columna' => 'der'],
        ['clave' => 'rotulo', 'nombre' => 'Rótulo Seguridad JN', 'columna' => 'der'],
        ['clave' => 'armero', 'nombre' => 'Armero', 'columna' => 'der'],
        ['clave' => 'colchon_catre', 'nombre' => 'Colchón y catre', 'columna' => 'der'],
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-bodega', only: ['show']),
            new Middleware('permission:manage-bodega', only: ['update', 'storeDevolucion', 'storeDevolucionUniforme']),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $proyectoId = $request->integer('proyecto_id') ?: null;
        $guardados = $proyectoId
            ? ProyectoRequerimientoEquipo::query()->where('proyecto_id', $proyectoId)->get()->keyBy('clave')
            : collect();

        $items = array_map(function (array $item) use ($guardados) {
            $row = $guardados->get($item['clave']);

            return [
                'clave' => $item['clave'],
                'nombre' => $item['nombre'],
                'columna' => $item['columna'],
                'requerido' => (bool) ($row->requerido ?? false),
                'cantidad' => (int) ($row->cantidad ?? 0),
            ];
        }, self::CATALOGO);

        $proyectos = Proyecto::query()
            ->whereIn('estado_proyecto', ['planificacion', 'activo', 'suspendido', 'finalizado'])
            ->orderBy('nombre_proyecto')
            ->get(['id', 'nombre_proyecto', 'correlativo', 'estado_proyecto']);

        $devoluciones = $proyectoId
            ? ProyectoRequerimientoDevolucion::query()
                ->with('items')
                ->where('proyecto_id', $proyectoId)
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
            : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'proyectos' => $proyectos,
                'items' => $items,
                'devoluciones' => $devoluciones,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $claves = collect(self::CATALOGO)->pluck('clave')->all();
        $data = $request->validate([
            'proyecto_id' => ['required', 'integer', 'exists:proyectos,id'],
            'items' => ['required', 'array'],
            'items.*.clave' => ['required', 'string', 'in:' . implode(',', $claves)],
            'items.*.cantidad' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items.*.requerido' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $cantidad = (int) ($item['cantidad'] ?? 0);
                $requerido = (bool) ($item['requerido'] ?? false) || $cantidad > 0;
                if (!$requerido) {
                    ProyectoRequerimientoEquipo::query()
                        ->where('proyecto_id', $data['proyecto_id'])
                        ->where('clave', $item['clave'])
                        ->delete();
                    continue;
                }

                ProyectoRequerimientoEquipo::query()->updateOrCreate(
                    [
                        'proyecto_id' => $data['proyecto_id'],
                        'clave' => $item['clave'],
                    ],
                    [
                        'requerido' => true,
                        'cantidad' => $cantidad > 0 ? $cantidad : 1,
                    ]
                );
            }
        });

        $request->merge(['proyecto_id' => $data['proyecto_id']]);

        return $this->show($request);
    }

    public function storeDevolucion(Request $request): JsonResponse
    {
        $claves = collect(self::CATALOGO)->pluck('clave')->all();
        $nombres = collect(self::CATALOGO)->keyBy('clave');
        $data = $request->validate([
            'proyecto_id' => ['required', 'integer', 'exists:proyectos,id'],
            'fecha' => ['required', 'date'],
            'nombres' => ['required', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.clave' => ['required', 'string', 'in:' . implode(',', $claves)],
            'items.*.devuelto' => ['required', 'boolean'],
        ]);

        $devolucion = DB::transaction(function () use ($data) {
            $devolucion = ProyectoRequerimientoDevolucion::query()->create([
                'proyecto_id' => $data['proyecto_id'],
                'fecha' => $data['fecha'],
                'nombres' => trim($data['nombres']),
                'observaciones' => $data['observaciones'] ?? null,
                'registrado_por_user_id' => Auth::id(),
            ]);
            foreach ($data['items'] as $item) {
                $devolucion->items()->create([
                    'clave' => $item['clave'],
                    'devuelto' => (bool) $item['devuelto'],
                ]);
            }

            return $devolucion->load('items');
        });

        $lineas = $devolucion->items->map(function ($item) use ($nombres) {
            $nombre = $nombres->get($item->clave)['nombre'] ?? $item->clave;

            return $nombre . ': ' . ($item->devuelto ? 'sí se devuelve' : 'no se devuelve');
        })->implode(', ');

        return response()->json([
            'success' => true,
            'message' => 'Devolución registrada. ' . $lineas,
            'data' => $devolucion,
        ]);
    }

    public function storeDevolucionUniforme(Request $request): JsonResponse
    {
        $claves = ['camisa', 'pantalon', 'chaleco', 'gorra', 'gorgorito', 'gafete', 'botas'];
        $data = $request->validate([
            'personal_id' => ['nullable', 'integer', 'exists:personal,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'fecha' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'prendas' => ['required', 'array', 'min:1'],
            'prendas.*.clave' => ['required', 'string', 'in:' . implode(',', $claves)],
            'prendas.*.devuelve' => ['required', 'boolean'],
            'prendas.*.talla' => ['nullable', 'string', 'max:20'],
        ]);

        $prendas = collect($data['prendas'])
            ->filter(fn ($prenda) => in_array($prenda['clave'], $claves, true))
            ->map(fn ($prenda) => [
                'clave' => $prenda['clave'],
                'devuelve' => (bool) $prenda['devuelve'],
                'talla' => $prenda['talla'] ?? null,
            ])
            ->values()
            ->all();

        if (!collect($prendas)->contains(fn ($prenda) => $prenda['devuelve'])) {
            return response()->json([
                'success' => false,
                'message' => 'Marca al menos una prenda que sí devuelve.',
            ], 422);
        }

        $registro = BodegaDevolucionUniforme::query()->create([
            'personal_id' => $data['personal_id'] ?? null,
            'nombre_persona' => trim($data['nombre']),
            'fecha' => $data['fecha'],
            'prendas' => $prendas,
            'observaciones' => $data['observaciones'] ?? null,
            'registrado_por_user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Devolución de uniforme registrada.',
            'data' => $registro,
        ], 201);
    }
}
