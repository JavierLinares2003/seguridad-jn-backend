<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BodegaArma;
use App\Services\BodegaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class BodegaArmaController extends Controller implements HasMiddleware
{
    public function __construct(private BodegaService $bodegaService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-bodega', only: ['index', 'catalogo', 'show']),
            new Middleware('permission:manage-bodega', only: ['store', 'update']),
        ];
    }

    public function catalogo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'tipos' => collect(BodegaArma::TIPOS)->map(fn ($label, $value) => [
                    'value' => $value,
                    'title' => $label,
                ])->values(),
                'estados' => collect(BodegaArma::ESTADOS)->map(fn ($label, $value) => [
                    'value' => $value,
                    'title' => $label,
                ])->values(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = BodegaArma::query()
            ->with([
                'proyecto:id,nombre_proyecto,correlativo',
                'personal:id,nombres,apellidos',
            ])
            ->orderBy('tipo')
            ->orderBy('codigo');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('proyecto_id')) {
            $query->where('proyecto_id', $request->input('proyecto_id'));
        }

        if ($request->boolean('solo_vencidas')) {
            $query->whereNotNull('vencimiento')->whereDate('vencimiento', '<', today());
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'ilike', "%{$search}%")
                    ->orWhere('codigo_interno', 'ilike', "%{$search}%")
                    ->orWhere('serie', 'ilike', "%{$search}%")
                    ->orWhere('marca', 'ilike', "%{$search}%")
                    ->orWhere('modelo', 'ilike', "%{$search}%")
                    ->orWhere('tenencia', 'ilike', "%{$search}%")
                    ->orWhere('portacion', 'ilike', "%{$search}%")
                    ->orWhere('responsable_nombre', 'ilike', "%{$search}%");
            });
        }

        $items = $query->get();

        $resumen = [
            'total' => $items->count(),
            'por_tipo' => collect(BodegaArma::TIPOS)->mapWithKeys(
                fn ($label, $tipo) => [$tipo => $items->where('tipo', $tipo)->count()]
            ),
            'en_bodega' => $items->where('estado', 'en_bodega')->count(),
            'asignadas' => $items->where('estado', 'asignada')->count(),
            'vencidas' => $items->where('alerta_vencimiento', 'vencida')->count(),
            'por_vencer' => $items->where('alerta_vencimiento', 'por_vencer')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => $resumen,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $arma = BodegaArma::with(['proyecto', 'personal'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $arma]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validar($request);
        $arma = BodegaArma::create($data);
        $this->bodegaService->asegurarCodigoArma($arma);
        $arma->load(['proyecto:id,nombre_proyecto,correlativo', 'personal:id,nombres,apellidos']);

        return response()->json([
            'success' => true,
            'message' => 'Arma registrada.',
            'data' => $arma,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $arma = BodegaArma::findOrFail($id);
        $arma->update($this->validar($request, $arma->id));
        $arma->load(['proyecto:id,nombre_proyecto,correlativo', 'personal:id,nombres,apellidos']);

        return response()->json([
            'success' => true,
            'message' => 'Arma actualizada.',
            'data' => $arma,
        ]);
    }

    private function validar(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'codigo_interno' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('bodega_armas', 'codigo_interno')->ignore($id),
            ],
            'tipo' => ['required', Rule::in(array_keys(BodegaArma::TIPOS))],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'serie' => [
                'required',
                'string',
                'max:80',
                Rule::unique('bodega_armas', 'serie')->ignore($id),
            ],
            'tenencia' => ['nullable', 'string', 'max:80'],
            'portacion' => ['nullable', 'string', 'max:80'],
            'vencimiento' => ['nullable', 'date'],
            'responsable_nombre' => ['nullable', 'string', 'max:150'],
            'personal_id' => ['nullable', 'exists:personal,id'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'estado' => ['nullable', Rule::in(array_keys(BodegaArma::ESTADOS))],
            'observaciones' => ['nullable', 'string'],
        ]);

        if (empty($data['estado'])) {
            $data['estado'] = (!empty($data['personal_id']) || !empty($data['proyecto_id']) || !empty($data['responsable_nombre']))
                ? 'asignada'
                : 'en_bodega';
        }

        return $data;
    }
}
