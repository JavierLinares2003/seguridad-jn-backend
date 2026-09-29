<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\PersonalPermiso;
use App\Models\PersonalVacacion;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
            'fechasReposicion',
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
        $data = $this->validatePermiso($request);

        $docData = [];
        if ($request->hasFile('documento')) {
            $docData = $this->guardarDocumento($request, $personal->id);
        }

        $compensaCon = $data['compensa_con'] ?? 'reposicion';
        $fechaRecuperacion = $data['fecha_recuperacion'] ?? null;
        $fechasReposicion = $compensaCon === 'reposicion'
            ? ($data['fechas_reposicion'] ?? [])
            : [];

        if ($compensaCon === 'vacaciones' && empty($fechaRecuperacion)) {
            $fechaRecuperacion = $data['fecha_inicio'];
        }

        if ($compensaCon === 'constancia') {
            $fechaRecuperacion = null;
        }

        $permiso = DB::transaction(function () use ($personal, $data, $docData, $compensaCon, $fechaRecuperacion, $fechasReposicion) {
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
                'registrado_por_user_id' => Auth::id(),
            ], $docData));

            $this->syncFechasReposicion($permiso, $fechasReposicion);
            $this->vincularVacacionSiAplica($personal, $permiso);

            return $permiso;
        });

        $permiso->load([
            'registradoPor:id,name',
            'reposiciones:id,permiso_reposicion_id,fecha_asistencia,horas_reposicion',
            'fechasReposicion',
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
            'fechasReposicion',
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

        $data = $this->validatePermiso($request, updating: true);

        if ($request->boolean('eliminar_documento') && $permiso->documento_ruta) {
            $this->permisosDisk()->delete($permiso->documento_ruta);
            $data['documento_ruta']            = null;
            $data['documento_nombre_original'] = null;
            $data['documento_extension']       = null;
            $data['documento_tamanio_kb']      = null;
        }

        if ($request->hasFile('documento')) {
            if ($permiso->documento_ruta) {
                $this->permisosDisk()->delete($permiso->documento_ruta);
            }
            $data = array_merge($data, $this->guardarDocumento($request, $personal->id));
        }

        unset($data['documento'], $data['eliminar_documento'], $data['fechas_reposicion']);

        $compensaCon = $data['compensa_con'] ?? $permiso->compensa_con ?? 'reposicion';
        $fechasReposicionInput = $request->has('fechas_reposicion')
            ? ($request->input('fechas_reposicion') ?? [])
            : null;

        if ($compensaCon === 'constancia') {
            $data['fecha_recuperacion'] = null;
            $fechasReposicionInput = [];
        } elseif ($compensaCon === 'vacaciones') {
            if (empty($data['fecha_recuperacion'] ?? $permiso->fecha_recuperacion)) {
                $data['fecha_recuperacion'] = $data['fecha_inicio'] ?? $permiso->fecha_inicio;
            }
            $fechasReposicionInput = [];
        }

        DB::transaction(function () use ($personal, $permiso, $data, $compensaCon, $fechasReposicionInput) {
            $permiso->update($data);

            if (is_array($fechasReposicionInput)) {
                $this->syncFechasReposicion($permiso, $compensaCon === 'reposicion' ? $fechasReposicionInput : []);
            }

            if (($permiso->compensa_con === 'vacaciones' || $permiso->fecha_recuperacion) && ! $permiso->vacacion_id) {
                $this->vincularVacacionSiAplica($personal, $permiso->fresh());
            }
        });

        $permiso->load([
            'registradoPor:id,name',
            'reposiciones:id,permiso_reposicion_id,fecha_asistencia,horas_reposicion',
            'fechasReposicion',
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

        if ($permiso->documento_ruta && $this->permisosDisk()->exists($permiso->documento_ruta)) {
            $this->permisosDisk()->delete($permiso->documento_ruta);
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

        if (! $permiso->documento_ruta || ! $this->permisosDisk()->exists($permiso->documento_ruta)) {
            return response()->json(['success' => false, 'message' => 'El documento no existe.'], 404);
        }

        return $this->permisosDisk()->download(
            $permiso->documento_ruta,
            $permiso->documento_nombre_original
        );
    }

    private function permisosDisk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('personal_permisos');

        return $disk;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function validatePermiso(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        $rules = [
            'tipo'               => [$required, 'in:horas,dias'],
            'cantidad_aprobada'  => [$required, 'numeric', 'min:0.5'],
            'fecha_inicio'       => [$required, 'date'],
            'fecha_fin'          => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'descripcion'        => [$required, 'string', 'max:1000'],
            'observaciones'      => ['nullable', 'string', 'max:1000'],
            'compensa_con'       => [$updating ? 'sometimes' : 'nullable', Rule::in(['reposicion', 'vacaciones', 'constancia'])],
            'fecha_recuperacion' => ['nullable', 'date'],
            'documento'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'fechas_reposicion'  => ['nullable', 'array'],
            'fechas_reposicion.*.fecha' => ['required_with:fechas_reposicion', 'date'],
            'fechas_reposicion.*.horas' => ['required_with:fechas_reposicion', 'numeric', 'min:0.25'],
        ];

        if ($updating) {
            $rules['eliminar_documento'] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        $compensaCon = $data['compensa_con'] ?? ($updating ? null : 'reposicion');

        // Al crear con reposición, exigir al menos una fecha de reposición.
        if (! $updating && ($compensaCon === 'reposicion' || $compensaCon === null)) {
            $request->validate([
                'fechas_reposicion' => ['required', 'array', 'min:1'],
                'fechas_reposicion.*.fecha' => ['required', 'date'],
                'fechas_reposicion.*.horas' => ['required', 'numeric', 'min:0.25'],
            ]);
            $data['fechas_reposicion'] = $request->input('fechas_reposicion', []);
        }

        if (($compensaCon === 'vacaciones' || $compensaCon === 'constancia') && isset($data['fechas_reposicion'])) {
            $data['fechas_reposicion'] = [];
        }

        return $data;
    }

    private function syncFechasReposicion(PersonalPermiso $permiso, array $filas): void
    {
        $permiso->fechasReposicion()->delete();

        foreach ($filas as $fila) {
            if (empty($fila['fecha']) || ! isset($fila['horas'])) {
                continue;
            }

            $permiso->fechasReposicion()->create([
                'fecha' => $fila['fecha'],
                'horas' => (float) $fila['horas'],
            ]);
        }
    }

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
            'registrado_por_user_id' => Auth::id(),
        ]);

        $permiso->update(['vacacion_id' => $vacacion->id]);
    }

    private function formatPermiso(PersonalPermiso $permiso): array
    {
        $baseUrl = config('app.url');
        $reposiciones = $permiso->relationLoaded('reposiciones') ? $permiso->reposiciones : collect();
        $fechasProgramadas = $permiso->relationLoaded('fechasReposicion')
            ? $permiso->fechasReposicion
            : $permiso->fechasReposicion()->get();

        $fechasReposicionFmt = $fechasProgramadas->map(fn ($f) => [
            'id'    => $f->id,
            'fecha' => $this->formatFecha($f->fecha),
            'horas' => (float) $f->horas,
        ])->values()->all();

        $fechasRecuperacion = collect($fechasReposicionFmt)
            ->pluck('fecha')
            ->filter()
            ->unique()
            ->values();

        foreach ($reposiciones as $rep) {
            $fecha = $this->formatFecha($rep->fecha_asistencia);
            if ($fecha && ! $fechasRecuperacion->contains($fecha)) {
                $fechasRecuperacion->push($fecha);
            }
        }

        $fechaManual = $this->formatFecha($permiso->fecha_recuperacion);
        if ($fechaManual && ! $fechasRecuperacion->contains($fechaManual)) {
            $fechasRecuperacion->push($fechaManual);
        }

        $repuestasAsistencia = $permiso->relationLoaded('reposiciones')
            ? (float) $reposiciones->sum('horas_reposicion')
            : (float) $permiso->horas_repuestas;
        $repuestasProgramadas = (float) collect($fechasReposicionFmt)->sum('horas');
        $repuestas = $repuestasAsistencia + $repuestasProgramadas;

        $esVacaciones = $permiso->compensa_con === 'vacaciones';
        $esConstancia = $permiso->compensa_con === 'constancia';
        $recuperado = $esVacaciones
            || $esConstancia
            || (bool) $permiso->fecha_recuperacion
            || $repuestas >= (float) $permiso->cantidad_aprobada;
        $saldo = ($esVacaciones || $esConstancia || $recuperado)
            ? 0
            : max(0, (float) $permiso->cantidad_aprobada - $repuestas);

        $compensaCon = $permiso->compensa_con ?: 'reposicion';
        $compensaLabel = match ($compensaCon) {
            'vacaciones' => 'Se toma como día de vacaciones',
            'constancia' => 'Presentó constancia',
            default      => 'Se recupera trabajando',
        };

        return [
            'id'                => $permiso->id,
            'tipo'              => $permiso->tipo,
            'cantidad_aprobada' => $permiso->cantidad_aprobada,
            'horas_repuestas'   => $repuestas,
            'saldo_pendiente'   => $saldo,
            'compensa_con'      => $compensaCon,
            'compensa_label'    => $compensaLabel,
            'fecha_recuperacion'=> $this->formatFecha($permiso->fecha_recuperacion),
            'fechas_recuperacion' => $fechasRecuperacion->values()->all(),
            'fechas_reposicion' => $fechasReposicionFmt,
            'recuperado'        => $recuperado,
            'es_vacaciones'     => $esVacaciones,
            'es_constancia'     => $esConstancia,
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
