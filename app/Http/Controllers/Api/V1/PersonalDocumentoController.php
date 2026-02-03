<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\DocumentoRequest;
use App\Models\Personal;
use App\Models\PersonalDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PersonalDocumentoController extends Controller
{
    /**
     * Listar documentos del personal
     */
    public function index(Personal $personal): JsonResponse
    {
        $documentos = $personal->documentos()
            ->with(['tipoDocumento', 'subidoPor:id,name'])
            ->orderBy('fecha_subida', 'desc')
            ->get()
            ->map(fn ($doc) => $this->formatDocumento($doc));

        return response()->json([
            'success' => true,
            'data' => $documentos,
        ]);
    }

    /**
     * Subir un nuevo documento
     */
    public function store(DocumentoRequest $request, Personal $personal): JsonResponse
    {
        $archivo = $request->file('archivo');
        $extension = strtolower($archivo->getClientOriginalExtension());
        $nombreOriginal = $archivo->getClientOriginalName();
        $tamanioKb = ceil($archivo->getSize() / 1024);

        // Generar nombre único para el archivo
        $nombreArchivo = sprintf(
            '%s_%s_%s.%s',
            $personal->id,
            $request->tipo_documento_personal_id,
            now()->format('YmdHis'),
            $extension
        );

        // Guardar archivo en storage
        $ruta = $archivo->storeAs(
            $personal->id,
            $nombreArchivo,
            'personal_documentos'
        );

        // Determinar estado inicial
        $estado = 'vigente';
        if ($request->fecha_vencimiento) {
            $diasParaVencer = now()->diffInDays($request->fecha_vencimiento, false);
            $diasAlerta = $request->dias_alerta_vencimiento ?? 30;

            if ($diasParaVencer < 0) {
                $estado = 'vencido';
            } elseif ($diasParaVencer <= $diasAlerta) {
                $estado = 'por_vencer';
            }
        }

        $documento = PersonalDocumento::create([
            'personal_id' => $personal->id,
            'tipo_documento_personal_id' => $request->tipo_documento_personal_id,
            'nombre_documento' => $request->nombre_documento ?? $nombreOriginal,
            'descripcion' => $request->descripcion,
            'ruta_archivo' => $ruta,
            'nombre_archivo_original' => $nombreOriginal,
            'extension' => $extension,
            'tamanio_kb' => $tamanioKb,
            'fecha_vencimiento' => $request->fecha_vencimiento,
            'fecha_subida' => now(),
            'subido_por_user_id' => auth()->id(),
            'estado_documento' => $estado,
            'dias_alerta_vencimiento' => $request->dias_alerta_vencimiento ?? 30,
        ]);

        $documento->load(['tipoDocumento', 'subidoPor:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Documento subido exitosamente.',
            'data' => $this->formatDocumento($documento),
        ], 201);
    }

    /**
     * Mostrar un documento específico
     */
    public function show(Personal $personal, PersonalDocumento $documento): JsonResponse
    {
        // Verificar que el documento pertenece al personal
        if ($documento->personal_id !== $personal->id) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no pertenece a este personal.',
            ], 404);
        }

        $documento->load(['tipoDocumento', 'subidoPor:id,name']);

        return response()->json([
            'success' => true,
            'data' => $this->formatDocumento($documento),
        ]);
    }

    /**
     * Descargar un documento
     */
    public function download(Personal $personal, PersonalDocumento $documento)
    {
        // Verificar que el documento pertenece al personal
        if ($documento->personal_id !== $personal->id) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no pertenece a este personal.',
            ], 404);
        }

        // Verificar que el archivo existe
        if (!Storage::disk('personal_documentos')->exists($documento->ruta_archivo)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo no existe en el servidor.',
            ], 404);
        }

        return Storage::disk('personal_documentos')->download(
            $documento->ruta_archivo,
            $documento->nombre_archivo_original
        );
    }

    /**
     * Eliminar un documento
     */
    public function destroy(Personal $personal, PersonalDocumento $documento): JsonResponse
    {
        // Verificar que el documento pertenece al personal
        if ($documento->personal_id !== $personal->id) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no pertenece a este personal.',
            ], 404);
        }

        // Eliminar archivo físico
        if (Storage::disk('personal_documentos')->exists($documento->ruta_archivo)) {
            Storage::disk('personal_documentos')->delete($documento->ruta_archivo);
        }

        // Soft delete del registro
        $documento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento eliminado exitosamente.',
        ]);
    }

    /**
     * Obtener documentos por estado
     */
    public function porEstado(Personal $personal, string $estado): JsonResponse
    {
        $estadosValidos = ['vigente', 'por_vencer', 'vencido'];

        if (!in_array($estado, $estadosValidos)) {
            return response()->json([
                'success' => false,
                'message' => 'Estado no válido. Estados permitidos: ' . implode(', ', $estadosValidos),
            ], 400);
        }

        $documentos = $personal->documentos()
            ->where('estado_documento', $estado)
            ->with(['tipoDocumento', 'subidoPor:id,name'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->get()
            ->map(fn ($doc) => $this->formatDocumento($doc));

        return response()->json([
            'success' => true,
            'data' => $documentos,
        ]);
    }

    /**
     * Obtener resumen de documentos del personal
     */
    public function resumen(Personal $personal): JsonResponse
    {
        $conteos = [
            'total' => $personal->documentos()->count(),
            'vigentes' => $personal->documentos()->vigentes()->count(),
            'por_vencer' => $personal->documentos()->porVencer()->count(),
            'vencidos' => $personal->documentos()->vencidos()->count(),
        ];

        $proximosAVencer = $personal->documentos()
            ->porVencer()
            ->with('tipoDocumento')
            ->orderBy('fecha_vencimiento', 'asc')
            ->limit(5)
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'nombre' => $doc->nombre_documento,
                'tipo' => $doc->tipoDocumento->nombre ?? null,
                'fecha_vencimiento' => $doc->fecha_vencimiento?->format('Y-m-d'),
                'dias_restantes' => $doc->fecha_vencimiento
                    ? now()->diffInDays($doc->fecha_vencimiento, false)
                    : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'conteos' => $conteos,
                'proximos_a_vencer' => $proximosAVencer,
            ],
        ]);
    }

    /**
     * Servir archivo para vista previa (inline)
     */
    public function preview(Personal $personal, PersonalDocumento $documento)
    {
        // Verificar que el documento pertenece al personal
        if ($documento->personal_id !== $personal->id) {
            return response()->json([
                'success' => false,
                'message' => 'El documento no pertenece a este personal.',
            ], 404);
        }

        // Verificar que el archivo existe
        if (!Storage::disk('personal_documentos')->exists($documento->ruta_archivo)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo no existe en el servidor.',
            ], 404);
        }

        // Obtener el contenido del archivo
        $file = Storage::disk('personal_documentos')->get($documento->ruta_archivo);
        $mimeType = Storage::disk('personal_documentos')->mimeType($documento->ruta_archivo);

        // Retornar el archivo con headers para vista inline
        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $documento->nombre_archivo_original . '"');
    }

    /**
     * Formatear documento para respuesta
     */
    private function formatDocumento(PersonalDocumento $documento): array
    {
        $baseUrl = config('app.url');
        
        return [
            'id' => $documento->id,
            'tipo_documento' => $documento->tipoDocumento ? [
                'id' => $documento->tipoDocumento->id,
                'nombre' => $documento->tipoDocumento->nombre,
            ] : null,
            'nombre_documento' => $documento->nombre_documento,
            'descripcion' => $documento->descripcion,
            'nombre_archivo_original' => $documento->nombre_archivo_original,
            'ruta_archivo' => $documento->ruta_archivo,
            'url' => "{$baseUrl}/api/v1/personal/{$documento->personal_id}/documentos/{$documento->id}/download",
            'url_preview' => "{$baseUrl}/api/v1/personal/{$documento->personal_id}/documentos/{$documento->id}/preview",
            'extension' => $documento->extension,
            'tamanio' => $documento->getTamanioFormateado(),
            'tamanio_kb' => $documento->tamanio_kb,
            'fecha_vencimiento' => $documento->fecha_vencimiento?->format('Y-m-d'),
            'fecha_subida' => $documento->fecha_subida?->format('Y-m-d H:i:s'),
            'estado_documento' => $documento->estado_documento,
            'dias_alerta_vencimiento' => $documento->dias_alerta_vencimiento,
            'subido_por' => $documento->subidoPor ? [
                'id' => $documento->subidoPor->id,
                'name' => $documento->subidoPor->name,
            ] : null,
            'dias_para_vencer' => $documento->fecha_vencimiento
                ? now()->diffInDays($documento->fecha_vencimiento, false)
                : null,
        ];
    }
}
