<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\FamiliarRequest;
use App\Http\Requests\Personal\PersonalDireccionRequest;
use App\Http\Requests\Personal\RedSocialRequest;
use App\Http\Requests\Personal\ReferenciaLaboralRequest;
use App\Http\Requests\Personal\StorePersonalRequest;
use App\Http\Requests\Personal\UpdatePersonalRequest;
use App\Http\Resources\PersonalCollection;
use App\Http\Resources\PersonalDireccionResource;
use App\Http\Resources\PersonalFamiliarResource;
use App\Http\Resources\PersonalReferenciaLaboralResource;
use App\Http\Resources\PersonalRedSocialResource;
use App\Http\Resources\PersonalResource;
use App\Models\Personal;
use App\Models\PersonalDireccion;
use App\Models\PersonalFamiliar;
use App\Models\PersonalReferenciaLaboral;
use App\Models\PersonalRedSocial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PersonalController extends Controller
{
    /**
     * Display a listing of personal with pagination and search.
     *
     * GET /api/v1/personal
     */
    public function index(Request $request): PersonalCollection|JsonResponse
    {
        $query = Personal::query()
            ->with([
                'estadoCivil:id,nombre',
                'tipoSangre:id,nombre',
                'sexo:id,nombre',
                'tipoContratacion:id,nombre',
                'tipoPago:id,nombre',
                'departamento:id,nombre',
            ])
            ->buscar($request->input('buscar'))
            ->byDepartamento($request->input('departamento_id'))
            ->byEstado($request->input('estado'));

        // Ordenamiento
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['nombres', 'apellidos', 'dpi', 'email', 'puesto', 'estado', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginación
        $perPage = min($request->input('per_page', 15), 100);
        $personal = $query->paginate($perPage);

        return new PersonalCollection($personal);
    }

    /**
     * Store a newly created personal.
     *
     * POST /api/v1/personal
     */
    public function store(StorePersonalRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Crear personal
            $personal = Personal::create($request->validated());

            // Crear dirección si se envió
            if ($request->has('direccion')) {
                $personal->direccion()->create($request->input('direccion'));
            }

            // Crear referencias laborales
            if ($request->has('referencias_laborales')) {
                foreach ($request->input('referencias_laborales') as $referencia) {
                    $personal->referenciasLaborales()->create($referencia);
                }
            }

            // Crear redes sociales
            if ($request->has('redes_sociales')) {
                foreach ($request->input('redes_sociales') as $red) {
                    $personal->redesSociales()->create($red);
                }
            }

            // Crear familiares
            if ($request->has('familiares')) {
                foreach ($request->input('familiares') as $familiar) {
                    $personal->familiares()->create($familiar);
                }
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $personal->load([
                'estadoCivil',
                'tipoSangre',
                'sexo',
                'tipoContratacion',
                'tipoPago',
                'departamento',
                'direccion.departamentoGeografico',
                'direccion.municipio',
                'referenciasLaborales',
                'redesSociales.redSocial',
                'familiares.parentesco',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Personal creado exitosamente.',
                'data' => new PersonalResource($personal),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el personal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified personal.
     *
     * GET /api/v1/personal/{id}
     */
    public function show(int $id): JsonResponse
    {
        $personal = Personal::with([
            'estadoCivil',
            'tipoSangre',
            'sexo',
            'tipoContratacion',
            'tipoPago',
            'departamento',
            'direccion.departamentoGeografico',
            'direccion.municipio',
            'referenciasLaborales',
            'redesSociales.redSocial',
            'familiares.parentesco',
            'documentos.tipoDocumento',
            'documentos.subidoPor',
        ])->find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PersonalResource($personal),
        ]);
    }

    /**
     * Generate CV PDF.
     *
     * GET /api/v1/personal/{id}/cv
     */
    public function generarCV(int $id)
    {
        $personal = Personal::with([
            'estadoCivil',
            'tipoSangre',
            'sexo',
            'tipoContratacion',
            'tipoPago',
            'departamento',
            'direccion.departamentoGeografico',
            'direccion.municipio',
            'referenciasLaborales',
            'redesSociales.redSocial',
            'familiares.parentesco',
            'documentos.tipoDocumento',
        ])->find($id);

        if (!$personal) {
            abort(404, 'Personal no encontrado.');
        }

        // Filter documents
        $documentos = $personal->documentos;
        
        $fotoPrincipal = $documentos->first(function ($doc) {
            if (!$doc->tipoDocumento) return false;
            $nombre = \Illuminate\Support\Str::slug($doc->tipoDocumento->nombre);
            // Check for variations of "Foto de Perfil"
            return str_contains($nombre, 'foto') && str_contains($nombre, 'perfil');
        });

        // Fallback: try to find any document that looks like a profile picture
        if (!$fotoPrincipal) {
             $fotoPrincipal = $documentos->first(function ($doc) {
                return \Illuminate\Support\Str::contains(strtolower($doc->nombre_documento), ['foto', 'perfil']);
            });
        }

        $fotografias = $documentos->filter(function ($doc) use ($fotoPrincipal) {
            if ($fotoPrincipal && $doc->id === $fotoPrincipal->id) return false;
            
            if ($doc->tipoDocumento) {
                $nombre = \Illuminate\Support\Str::slug($doc->tipoDocumento->nombre);
                if ($nombre === 'fotografia' || str_contains($nombre, 'fotografia')) return true;
            }
            
            return false;
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.cv', [
            'personal' => $personal,
            'fotoPrincipal' => $fotoPrincipal,
            'fotografias' => $fotografias,
        ]);
        
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('CV-' . \Illuminate\Support\Str::slug($personal->nombres . '-' . $personal->apellidos) . '.pdf');
    }

    /**
     * Update the specified personal.
     *
     * PUT /api/v1/personal/{id}
     */
    public function update(UpdatePersonalRequest $request, int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Actualizar datos del personal
            $personal->update($request->validated());

            // Actualizar o crear dirección
            if ($request->has('direccion')) {
                $personal->direccion()->updateOrCreate(
                    ['personal_id' => $personal->id],
                    $request->input('direccion')
                );
            }

            // Actualizar referencias laborales
            if ($request->has('referencias_laborales')) {
                $this->syncRelatedItems(
                    $personal,
                    'referenciasLaborales',
                    $request->input('referencias_laborales'),
                    PersonalReferenciaLaboral::class
                );
            }

            // Actualizar redes sociales
            if ($request->has('redes_sociales')) {
                $this->syncRelatedItems(
                    $personal,
                    'redesSociales',
                    $request->input('redes_sociales'),
                    PersonalRedSocial::class
                );
            }

            // Actualizar familiares
            if ($request->has('familiares')) {
                $this->syncRelatedItems(
                    $personal,
                    'familiares',
                    $request->input('familiares'),
                    PersonalFamiliar::class
                );
            }

            DB::commit();

            // Recargar relaciones
            $personal->load([
                'estadoCivil',
                'tipoSangre',
                'sexo',
                'tipoContratacion',
                'tipoPago',
                'departamento',
                'direccion.departamentoGeografico',
                'direccion.municipio',
                'referenciasLaborales',
                'redesSociales.redSocial',
                'familiares.parentesco',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Personal actualizado exitosamente.',
                'data' => new PersonalResource($personal),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el personal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified personal (soft delete).
     *
     * DELETE /api/v1/personal/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $personal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Personal eliminado exitosamente.',
        ]);
    }

    /**
     * Restore a soft-deleted personal.
     *
     * POST /api/v1/personal/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        $personal = Personal::withTrashed()->find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        if (!$personal->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'El personal no está eliminado.',
                'error' => 'not_deleted',
            ], 400);
        }

        $personal->restore();

        return response()->json([
            'success' => true,
            'message' => 'Personal restaurado exitosamente.',
            'data' => new PersonalResource($personal),
        ]);
    }

    /**
     * Change personal status.
     *
     * PATCH /api/v1/personal/{id}/estado
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => ['required', 'in:activo,inactivo,suspendido'],
        ]);

        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $personal->update(['estado' => $request->estado]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente.',
            'data' => [
                'id' => $personal->id,
                'estado' => $personal->estado,
            ],
        ]);
    }

    /**
     * Update or create personal address.
     *
     * PUT /api/v1/personal/{id}/direccion
     */
    public function updateDireccion(PersonalDireccionRequest $request, int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $direccion = $personal->direccion()->updateOrCreate(
            ['personal_id' => $personal->id],
            $request->validated()
        );

        $direccion->load(['departamentoGeografico', 'municipio']);

        return response()->json([
            'success' => true,
            'message' => 'Dirección actualizada exitosamente.',
            'data' => new PersonalDireccionResource($direccion),
        ]);
    }

    /**
     * Get personal address.
     *
     * GET /api/v1/personal/{id}/direccion
     */
    public function getDireccion(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $direccion = $personal->direccion;

        if (!$direccion) {
            return response()->json([
                'success' => false,
                'message' => 'El personal no tiene dirección registrada.',
                'error' => 'not_found',
            ], 404);
        }

        $direccion->load(['departamentoGeografico', 'municipio']);

        return response()->json([
            'success' => true,
            'data' => new PersonalDireccionResource($direccion),
        ]);
    }

    /**
     * Delete personal address.
     *
     * DELETE /api/v1/personal/{id}/direccion
     */
    public function deleteDireccion(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $direccion = $personal->direccion;

        if (!$direccion) {
            return response()->json([
                'success' => false,
                'message' => 'El personal no tiene dirección registrada.',
                'error' => 'not_found',
            ], 404);
        }

        $direccion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dirección eliminada exitosamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Referencias Laborales
    |--------------------------------------------------------------------------
    */

    /**
     * Get all referencias laborales for a personal.
     *
     * GET /api/v1/personal/{id}/referencias
     */
    public function getReferencias(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $referencias = $personal->referenciasLaborales()
            ->orderBy('fecha_inicio', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PersonalReferenciaLaboralResource::collection($referencias),
            'total' => $referencias->count(),
        ]);
    }

    /**
     * Store a new referencia laboral.
     *
     * POST /api/v1/personal/{id}/referencias
     */
    public function storeReferencia(ReferenciaLaboralRequest $request, int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $referencia = $personal->referenciasLaborales()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Referencia laboral creada exitosamente.',
            'data' => new PersonalReferenciaLaboralResource($referencia),
        ], 201);
    }

    /**
     * Get a specific referencia laboral.
     *
     * GET /api/v1/personal/{id}/referencias/{referenciaId}
     */
    public function showReferencia(int $id, int $referenciaId): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $referencia = $personal->referenciasLaborales()->find($referenciaId);

        if (!$referencia) {
            return response()->json([
                'success' => false,
                'message' => 'Referencia laboral no encontrada.',
                'error' => 'not_found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PersonalReferenciaLaboralResource($referencia),
        ]);
    }

    /**
     * Update a referencia laboral.
     *
     * PUT /api/v1/personal/{id}/referencias/{referenciaId}
     */
    public function updateReferencia(ReferenciaLaboralRequest $request, int $id, int $referenciaId): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $referencia = $personal->referenciasLaborales()->find($referenciaId);

        if (!$referencia) {
            return response()->json([
                'success' => false,
                'message' => 'Referencia laboral no encontrada.',
                'error' => 'not_found',
            ], 404);
        }

        $referencia->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Referencia laboral actualizada exitosamente.',
            'data' => new PersonalReferenciaLaboralResource($referencia),
        ]);
    }

    /**
     * Delete a referencia laboral.
     *
     * DELETE /api/v1/personal/{id}/referencias/{referenciaId}
     */
    public function deleteReferencia(int $id, int $referenciaId): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $referencia = $personal->referenciasLaborales()->find($referenciaId);

        if (!$referencia) {
            return response()->json([
                'success' => false,
                'message' => 'Referencia laboral no encontrada.',
                'error' => 'not_found',
            ], 404);
        }

        $referencia->delete();

        return response()->json([
            'success' => true,
            'message' => 'Referencia laboral eliminada exitosamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Familiares
    |--------------------------------------------------------------------------
    */

    /**
     * Get all familiares for a personal.
     *
     * GET /api/v1/personal/{id}/familiares
     */
    public function getFamiliares(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $familiares = $personal->familiares()
            ->with('parentesco')
            ->orderBy('es_contacto_emergencia', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PersonalFamiliarResource::collection($familiares),
            'total' => $familiares->count(),
        ]);
    }

    /**
     * Store a new familiar.
     *
     * POST /api/v1/personal/{id}/familiares
     */
    public function storeFamiliar(FamiliarRequest $request, int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $familiar = $personal->familiares()->create($request->validated());
        $familiar->load('parentesco');

        return response()->json([
            'success' => true,
            'message' => 'Familiar creado exitosamente.',
            'data' => new PersonalFamiliarResource($familiar),
        ], 201);
    }

    /**
     * Update a familiar.
     *
     * PUT /api/v1/personal/{id}/familiares/{familiarId}
     */
    public function updateFamiliar(FamiliarRequest $request, int $id, int $familiarId): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $familiar = $personal->familiares()->find($familiarId);

        if (!$familiar) {
            return response()->json([
                'success' => false,
                'message' => 'Familiar no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $familiar->update($request->validated());
        $familiar->load('parentesco');

        return response()->json([
            'success' => true,
            'message' => 'Familiar actualizado exitosamente.',
            'data' => new PersonalFamiliarResource($familiar),
        ]);
    }

    /**
     * Delete a familiar.
     *
     * DELETE /api/v1/personal/{id}/familiares/{familiarId}
     */
    public function deleteFamiliar(int $id, int $familiarId): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $familiar = $personal->familiares()->find($familiarId);

        if (!$familiar) {
            return response()->json([
                'success' => false,
                'message' => 'Familiar no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $familiar->delete();

        return response()->json([
            'success' => true,
            'message' => 'Familiar eliminado exitosamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Redes Sociales
    |--------------------------------------------------------------------------
    */

    /**
     * Get all redes sociales for a personal.
     *
     * GET /api/v1/personal/{id}/redes-sociales
     */
    public function getRedesSociales(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $redesSociales = $personal->redesSociales()
            ->with('redSocial')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PersonalRedSocialResource::collection($redesSociales),
            'total' => $redesSociales->count(),
        ]);
    }

    /**
     * Store a new red social.
     *
     * POST /api/v1/personal/{id}/redes-sociales
     */
    public function storeRedSocial(RedSocialRequest $request, int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $redSocial = $personal->redesSociales()->create($request->validated());
        $redSocial->load('redSocial');

        return response()->json([
            'success' => true,
            'message' => 'Red social creada exitosamente.',
            'data' => new PersonalRedSocialResource($redSocial),
        ], 201);
    }

    /**
     * Update a red social.
     *
     * PUT /api/v1/personal/{id}/redes-sociales/{redSocialId}
     */
    public function updateRedSocial(RedSocialRequest $request, int $id, int $redSocialId): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $redSocial = $personal->redesSociales()->find($redSocialId);

        if (!$redSocial) {
            return response()->json([
                'success' => false,
                'message' => 'Red social no encontrada.',
                'error' => 'not_found',
            ], 404);
        }

        $redSocial->update($request->validated());
        $redSocial->load('redSocial');

        return response()->json([
            'success' => true,
            'message' => 'Red social actualizada exitosamente.',
            'data' => new PersonalRedSocialResource($redSocial),
        ]);
    }

    /**
     * Delete a red social.
     *
     * DELETE /api/v1/personal/{id}/redes-sociales/{redSocialId}
     */
    public function deleteRedSocial(int $id, int $redSocialId): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        $redSocial = $personal->redesSociales()->find($redSocialId);

        if (!$redSocial) {
            return response()->json([
                'success' => false,
                'message' => 'Red social no encontrada.',
                'error' => 'not_found',
            ], 404);
        }

        $redSocial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Red social eliminada exitosamente.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Foto de Perfil
    |--------------------------------------------------------------------------
    */

    /**
     * Upload or update personal profile photo.
     *
     * POST /api/v1/personal/{id}/foto
     */
    public function uploadFoto(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png,gif', 'max:5120'], // 5MB max
        ]);

        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        try {
            // Eliminar foto anterior si existe
            if ($personal->foto_perfil && Storage::disk('personal_fotos')->exists($personal->foto_perfil)) {
                Storage::disk('personal_fotos')->delete($personal->foto_perfil);
            }

            // Guardar nueva foto
            $file = $request->file('foto');
            $extension = $file->getClientOriginalExtension();
            $filename = $personal->id . '_' . time() . '.' . $extension;
            
            $path = $file->storeAs('', $filename, 'personal_fotos');

            // Actualizar registro
            $personal->update(['foto_perfil' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Foto de perfil actualizada exitosamente.',
                'data' => [
                    'foto_perfil' => $path,
                    'foto_url' => Storage::disk('personal_fotos')->url($path),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir la foto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete personal profile photo.
     *
     * DELETE /api/v1/personal/{id}/foto
     */
    public function deleteFoto(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        if (!$personal->foto_perfil) {
            return response()->json([
                'success' => false,
                'message' => 'El personal no tiene foto de perfil.',
                'error' => 'not_found',
            ], 404);
        }

        try {
            // Eliminar archivo
            if (Storage::disk('personal_fotos')->exists($personal->foto_perfil)) {
                Storage::disk('personal_fotos')->delete($personal->foto_perfil);
            }

            // Actualizar registro
            $personal->update(['foto_perfil' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Foto de perfil eliminada exitosamente.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la foto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync related items (create, update, delete).
     */
    private function syncRelatedItems(Personal $personal, string $relation, array $items, string $modelClass): void
    {
        $existingIds = [];

        foreach ($items as $item) {
            // Si tiene _delete marcado, eliminar
            if (!empty($item['_delete']) && !empty($item['id'])) {
                $modelClass::where('id', $item['id'])
                    ->where('personal_id', $personal->id)
                    ->delete();
                continue;
            }

            unset($item['_delete']);

            if (!empty($item['id'])) {
                // Actualizar existente
                $modelClass::where('id', $item['id'])
                    ->where('personal_id', $personal->id)
                    ->update($item);
                $existingIds[] = $item['id'];
            } else {
                // Crear nuevo
                $newItem = $personal->$relation()->create($item);
                $existingIds[] = $newItem->id;
            }
        }
    }

    /**
     * Get historial de proyectos asignados al personal.
     *
     * GET /api/v1/personal/{id}/proyectos
     */
    public function getHistorialProyectos(int $id): JsonResponse
    {
        $personal = Personal::find($id);

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Personal no encontrado.',
                'error' => 'not_found',
            ], 404);
        }

        // Obtener asignaciones con relaciones
        $asignaciones = \App\Models\OperacionPersonalAsignado::where('personal_id', $id)
            ->with([
                'proyecto:id,nombre_proyecto,correlativo,empresa_cliente,estado_proyecto',
                'configuracionPuesto:id,nombre_puesto,tipo_personal_id',
                'configuracionPuesto.tipoPersonal:id,nombre',
                'turno:id,nombre',
            ])
            ->orderBy('fecha_inicio', 'desc')
            ->get()
            ->map(function ($asignacion) {
                return [
                    'id' => $asignacion->id,
                    'proyecto' => [
                        'id' => $asignacion->proyecto->id ?? null,
                        'nombre' => $asignacion->proyecto->nombre_proyecto ?? 'N/A',
                        'correlativo' => $asignacion->proyecto->correlativo ?? null,
                        'empresa_cliente' => $asignacion->proyecto->empresa_cliente ?? null,
                        'estado' => $asignacion->proyecto->estado_proyecto ?? null,
                    ],
                    'tipo_personal' => [
                        'id' => $asignacion->configuracionPuesto?->tipoPersonal?->id ?? null,
                        'nombre' => $asignacion->configuracionPuesto?->tipoPersonal?->nombre ?? 'N/A',
                    ],
                    'turno' => $asignacion->turno->nombre ?? 'N/A',
                    'fecha_inicio' => $asignacion->fecha_inicio,
                    'fecha_fin' => $asignacion->fecha_fin,
                    'estado_asignacion' => $asignacion->estado_asignacion,
                    'notas' => $asignacion->notas,
                    'dias_trabajados' => $asignacion->fecha_inicio && $asignacion->fecha_fin
                        ? \Carbon\Carbon::parse($asignacion->fecha_inicio)->diffInDays(\Carbon\Carbon::parse($asignacion->fecha_fin)) + 1
                        : ($asignacion->fecha_inicio ? \Carbon\Carbon::parse($asignacion->fecha_inicio)->diffInDays(now()) + 1 : 0),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $asignaciones,
            'total' => $asignaciones->count(),
        ]);
    }
}
