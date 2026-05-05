<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Catalogos\Departamento;
use App\Models\VacacionConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacacionConfigController extends Controller
{
    /**
     * Listar la configuración de vacaciones por departamento.
     * Incluye todos los departamentos activos con su config (o null si no tiene).
     */
    public function index(): JsonResponse
    {
        $departamentos = Departamento::activos()->orderBy('nombre')->get();

        $defaultConfig   = VacacionConfig::whereNull('departamento_id')->first();
        $diasDefault     = $defaultConfig?->dias_por_anio ?? 8;
        $configsPorDepto = VacacionConfig::whereNotNull('departamento_id')
            ->get()
            ->keyBy('departamento_id');

        $resultado = $departamentos->map(function ($depto) use ($configsPorDepto, $diasDefault) {
            $config = $configsPorDepto->get($depto->id);
            return [
                'departamento_id'     => $depto->id,
                'departamento_nombre' => $depto->nombre,
                'config_id'           => $config?->id,
                'dias_por_anio'       => $config?->dias_por_anio ?? $diasDefault,
                'tiene_config_propia' => $config !== null,
                'descripcion'         => $config?->descripcion,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'departamentos' => $resultado,
                'default' => [
                    'config_id'     => $defaultConfig?->id,
                    'dias_por_anio' => $diasDefault,
                    'descripcion'   => $defaultConfig?->descripcion,
                ],
            ],
        ]);
    }

    /**
     * Crear o actualizar la config para un departamento (o el default si departamento_id es null).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departamento_id' => 'nullable|integer|exists:departamentos,id',
            'dias_por_anio'   => 'required|integer|min:1|max:365',
            'descripcion'     => 'nullable|string|max:255',
        ]);

        $config = VacacionConfig::updateOrCreate(
            ['departamento_id' => $data['departamento_id'] ?? null],
            [
                'dias_por_anio' => $data['dias_por_anio'],
                'descripcion'   => $data['descripcion'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Configuración guardada.',
            'data'    => $config,
        ], 201);
    }

    /**
     * Actualizar una config existente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $config = VacacionConfig::findOrFail($id);

        $data = $request->validate([
            'dias_por_anio' => 'required|integer|min:1|max:365',
            'descripcion'   => 'nullable|string|max:255',
        ]);

        $config->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada.',
            'data'    => $config,
        ]);
    }

    /**
     * Eliminar la config de un departamento (vuelve al default).
     */
    public function destroy(int $id): JsonResponse
    {
        $config = VacacionConfig::findOrFail($id);

        if ($config->departamento_id === null) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la configuración default. Actualice los días en su lugar.',
            ], 422);
        }

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Configuración eliminada. El departamento usará los días por defecto.',
        ]);
    }
}
