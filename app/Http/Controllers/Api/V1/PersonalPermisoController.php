<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\PersonalPermiso;
use App\Models\PersonalVacacion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PersonalPermisoController extends Controller
{
    /**
     * GET /api/v1/personal/{personal}/permisos
     * Lista permisos del empleado con saldo de reposición.
     */
    public function index(Request $request, Personal $personal): JsonResponse
    {
        $query = $personal->permisos()->with([
            'registradoPor:id,name',
            'reposiciones:id,permiso_reposicion_id,fecha_asistencia,horas_reposicion',
        ]);

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('con_saldo')) {
            $query->conSaldoPendiente();
        }

        $permisos = $query->orderBy('fecha_inicio', 'desc')->get()
            ->map(fn ($p) => $this->formatPermiso($p));

        return response()->json([
            'success' => true,
            'data'    => $permisos,
        ]);
    }

    /**
     * POST /api/v1/personal/{personal}/permisos
     * Registrar un permiso de ausencia aprobado.
     */
    public function store(Request $request, Personal $personal): JsonResponse
    {
        $data = $request->validate([
            'tipo'              => 'required|in:horas,dias',
            'cantidad_aprobada' => 'required|numeric|min:0.5',
            'fecha_inicio'      => 'required|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_inicio',
            'descripcion'       => 'required|string|max:1000',
            'observaciones'     => 'nullable|string|max:1000',
            'compensa_con'      => 'nullable|in:reposicion,vacaciones',
            'fecha_recuperacion'=> 'nullable|date',
            'documento'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $docData = [];
        if ($request->hasFile('documento')) {
            $docData = $this->guardarDocumento($request, $personal->id);
        }

        $compensaCon = $data['compensa_con'] ?? 'reposicion';
        $fechaRecuperacion = $data['fecha_recuperacion'] ?? null;
        if ($compensaCon === 'vacaciones' && empty($fechaRecuperacion)) {
            $fechaRecuperacion = $data['fecha_inicio'];
        }

        $permiso = PersonalPermiso::create(array_merge([
            'personal_id'            => $personal->id,
            'tipo'                   => $data['tipo'],
            'cantidad_aprobada'      => $data['cantidad_aprobada'],
            'fecha_inicio'           => $data['fecha_inicio'],
            'fecha_fin'              => $data['fecha_fin'] ?? null,
            'descripcion'            => $data['descripcion'],
            'observaciones'          => $data['observaciones'] ?? null,
            'compensa_con'           => $compensaCon,
            'fecha_recuperacion'     => $fechaRecuperacion,
            'registrado_por_user_id' => auth()->id(),
        ], $docData));

        $this->vincularVacacionSiAplica($personal, $permiso);

        $permiso->load([
            'registradoPor:id,name',
            'reposiciones:id,permiso_reposicion_id,fecha_asistencia,horas_reposicion',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permiso de ausencia registrado exitosamente.',
            'data'    => $this->formatPermiso($permiso),
        ], 201);
    }

    /**
     * GET /api/v1/personal/{personal}/permisos/{permiso}
     * Detalle de un permiso con historial de reposiciones.
     */
    public function show(Personal $personal, PersonalPermiso $permiso): JsonResponse
    {
        if ($permiso->personal_id !== $personal->id) {
            return response()->json(['success' => false, 'message' => 'Permiso no encontrado.'], 404);
        }

        $permiso->load([
            'registradoPor:id,name',
            'reposiciones.asignacion.proyecto',
            'ausenciasVinculadas',
        ]);

        $reposiciones = $permiso->reposiciones->map(fn ($a) => [
            'id'               => $a->id,
            'fecha'            => $this->formatFecha($a->fecha_asistencia),
            'horas_reposicion' => $a->horas_reposicion,
            'proyecto'         => $a->asignacion?->proyecto?->nombre_proyecto,
        ]);

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->formatPermiso($permiso), [
                'reposiciones'     => $reposiciones,
                'ausencias_count'  => $permiso->ausenciasVinculadas->count(),
            ]),
        ]);
    }

    /**
     * PUT /api/v1/personal/{personal}/permisos/{permiso}
     */
    public function update(Request $request, Personal $personal, PersonalPermiso $permiso): JsonResponse
    {
        if ($permiso->personal_id !== $personal->id) {
            return response()->json(['success' => false, 'message' => 'Permiso no encontrado.'], 404);
        }

        $data = $request->validate([
            'tipo'              => 'sometimes|in:horas,dias',
            'cantidad_aprobada' => 'sometimes|numeric|min:0.5',
            'fecha_inicio'      => 'sometimes|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_inicio',
            'descripcion'       => 'sometimes|string|max:1000',
            'observaciones'     => 'nullable|string|max:1000',
            'compensa_con'      => 'sometimes|in:reposicion,vacaciones',
            'fecha_recuperacion'=> 'nullable|date',
            'documento'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'eliminar_documento'=> 'nullable|boolean',
        ]);

        if ($request->boolean('eliminar_documento') && $permiso->documento_ruta) {
            Storage::disk('personal_permisos')->delete($permiso->documento_ruta);
            $data['documento_ruta']            = null;
            $data['documento_nombre_original'] = null;
            $data['documento_extension']       = null;
            $data['documento_tamanio_kb']      = null;
        }

        if ($request->hasFile('documento')) {
            if ($permiso->documento_ruta) {
                Storage::disk('personal_permisos')->delete($permiso->documento_ruta);
            }
            $data = array_merge($data, $this->guardarDocumento($request, $personal->id));
        }

        unset($data['documento'], $data['eliminar_documento']);
        $permiso->update($data);

        if (($permiso->compensa_con === 'vacaciones' || $permiso->fecha_recuperacion) && ! $permiso->vacacion_id) {
            $this->vincularVacacionSiAplica($personal, $permiso);
        }

        $permiso->load([
            'registradoPor:id,name',
            'reposiciones:id,permiso_reposicion_id,fecha_asistencia,horas_reposicion',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permiso actualizado.',
            'data'    => $this->formatPermiso($permiso),
        ]);
    }

    /**
     * DELETE /api/v1/personal/{personal}/permisos/{permiso}
     */
    public function destroy(Personal $personal, PersonalPermiso $permiso): JsonResponse
    {
        if ($permiso->personal_id !== $personal->id) {
            return response()->json(['success' => false, 'message' => 'Permiso no encontrado.'], 404);
        }

        if ($permiso->documento_ruta && Storage::disk('personal_permisos')->exists($permiso->documento_ruta)) {
            Storage::disk('personal_permisos')->delete($permiso->documento_ruta);
        }

        $permiso->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permiso eliminado.',
        ]);
    }

    /**
     * GET /api/v1/personal/{personal}/permisos/{permiso}/documento
     */
    public function downloadDocumento(Personal $personal, PersonalPermiso $permiso)
    {
        if ($permiso->personal_id !== $personal->id) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        if (! $permiso->documento_ruta || ! Storage::disk('personal_permisos')->exists($permiso->documento_ruta)) {
            return response()->json(['success' => false, 'message' => 'El documento no existe.'], 404);
        }

        return Storage::disk('personal_permisos')->download(
            $permiso->documento_ruta,
            $permiso->documento_nombre_original
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function guardarDocumento(Request $request, int $personalId): array
    {
        $archivo        = $request->file('documento');
        $extension      = strtolower($archivo->getClientOriginalExtension());
        $nombreOriginal = $archivo->getClientOriginalName();
        $tamanioKb      = (int) ceil($archivo->getSize() / 1024);
        $nombreArchivo  = sprintf('%s_permiso_%s.%s', $personalId, now()->format('YmdHis'), $extension);

        $ruta = $archivo->storeAs($personalId, $nombreArchivo, 'personal_permisos');

        return [
            'documento_ruta'            => $ruta,
            'documento_nombre_original' => $nombreOriginal,
            'documento_extension'       => $extension,
            'documento_tamanio_kb'      => $tamanioKb,
        ];
    }

    private function vincularVacacionSiAplica(Personal $personal, PersonalPermiso $permiso): void
    {
        if ($permiso->vacacion_id || $permiso->compensa_con !== 'vacaciones' || $permiso->tipo !== 'dias') {
            return;
        }

        $dias = max(1, (int) ceil((float) $permiso->cantidad_aprobada));
        $vacacion = PersonalVacacion::create([
            'personal_id'            => $personal->id,
            'anio'                   => Carbon::parse($permiso->fecha_inicio)->year,
            'fecha_inicio'           => $permiso->fecha_inicio,
            'fecha_fin'              => $permiso->fecha_fin,
            'dias_solicitados'       => $dias,
            'dias_aprobados'         => $dias,
            'descripcion'            => 'Permiso tomado como vacaciones: ' . ($permiso->descripcion ?: 'sin detalle'),
            'observaciones'          => 'Generado desde permiso #' . $permiso->id,
            'registrado_por_user_id' => auth()->id(),
        ]);

        $permiso->update(['vacacion_id' => $vacacion->id]);
    }

    private function formatPermiso(PersonalPermiso $permiso): array
    {
        $baseUrl = config('app.url');
        $reposiciones = $permiso->relationLoaded('reposiciones') ? $permiso->reposiciones : collect();
        $fechasRecuperacion = $reposiciones
            ->pluck('fecha_asistencia')
            ->filter()
            ->map(fn ($f) => $this->formatFecha($f))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $fechaManual = $this->formatFecha($permiso->fecha_recuperacion);
        if ($fechaManual && ! in_array($fechaManual, $fechasRecuperacion, true)) {
            $fechasRecuperacion[] = $fechaManual;
        }

        $repuestas = $permiso->relationLoaded('reposiciones')
            ? (float) $reposiciones->sum('horas_reposicion')
            : (float) $permiso->horas_repuestas;

        $esVacaciones = $permiso->compensa_con === 'vacaciones';
        $recuperado = $esVacaciones || (bool) $permiso->fecha_recuperacion || $repuestas >= (float) $permiso->cantidad_aprobada;
        $saldo = $recuperado ? 0 : max(0, (float) $permiso->cantidad_aprobada - $repuestas);

        return [
            'id'                => $permiso->id,
            'tipo'              => $permiso->tipo,
            'cantidad_aprobada' => $permiso->cantidad_aprobada,
            'horas_repuestas'   => $repuestas,
            'saldo_pendiente'   => $saldo,
            'compensa_con'      => $permiso->compensa_con ?: 'reposicion',
            'fecha_recuperacion'=> $this->formatFecha($permiso->fecha_recuperacion),
            'fechas_recuperacion' => $fechasRecuperacion,
            'recuperado'        => $recuperado,
            'es_vacaciones'     => $esVacaciones,
            'fecha_inicio'      => $this->formatFecha($permiso->fecha_inicio),
            'fecha_fin'         => $this->formatFecha($permiso->fecha_fin),
            'descripcion'       => $permiso->descripcion,
            'observaciones'     => $permiso->observaciones,
            'tiene_documento'   => $permiso->tiene_documento,
            'documento_nombre'  => $permiso->documento_nombre_original,
            'documento_extension' => $permiso->documento_extension,
            'documento_tamanio_kb' => $permiso->documento_tamanio_kb,
            'url_documento'     => $permiso->tiene_documento
                ? "{$baseUrl}/api/v1/personal/{$permiso->personal_id}/permisos/{$permiso->id}/documento"
                : null,
            'registrado_por'    => $permiso->registradoPor ? [
                'id'   => $permiso->registradoPor->id,
                'name' => $permiso->registradoPor->name,
            ] : null,
            'created_at'        => $this->formatFechaHora($permiso->created_at),
        ];
    }

    private function formatFecha(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        return Carbon::parse($fecha)->format('Y-m-d');
    }

    private function formatFechaHora(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        return Carbon::parse($fecha)->format('Y-m-d H:i:s');
    }
}
