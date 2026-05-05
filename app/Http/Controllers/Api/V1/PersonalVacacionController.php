<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\PersonalVacacion;
use App\Models\VacacionConfig;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PersonalVacacionController extends Controller
{
    /**
     * Listar vacaciones de un empleado, con resumen de saldo.
     */
    public function index(Request $request, Personal $personal): JsonResponse
    {
        $query = $personal->vacaciones()->with('registradoPor:id,name');

        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }

        $vacaciones = $query->orderBy('anio', 'desc')->orderBy('created_at', 'desc')->get()
            ->map(fn ($v) => $this->formatVacacion($v));

        $saldo = $this->calcularSaldo($personal);

        return response()->json([
            'success' => true,
            'data' => [
                'vacaciones' => $vacaciones,
                'saldo'      => $saldo,
            ],
        ]);
    }

    /**
     * Registrar una vacación aprobada (con documento opcional).
     */
    public function store(Request $request, Personal $personal): JsonResponse
    {
        $data = $request->validate([
            'fecha_inicio'     => 'nullable|date',
            'fecha_fin'        => 'nullable|date|after_or_equal:fecha_inicio',
            'dias_solicitados' => 'required|integer|min:1',
            'dias_aprobados'   => 'required|integer|min:0',
            'descripcion'      => 'nullable|string|max:1000',
            'observaciones'    => 'nullable|string|max:1000',
            'documento'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Derivar el año automáticamente desde fecha_inicio, o usar el año actual
        $anio = isset($data['fecha_inicio'])
            ? Carbon::parse($data['fecha_inicio'])->year
            : now()->year;

        $docData = [];
        if ($request->hasFile('documento')) {
            $archivo    = $request->file('documento');
            $extension  = strtolower($archivo->getClientOriginalExtension());
            $nombreOriginal = $archivo->getClientOriginalName();
            $tamanioKb  = (int) ceil($archivo->getSize() / 1024);
            $nombreArchivo = sprintf('%s_vacacion_%s.%s', $personal->id, now()->format('YmdHis'), $extension);

            $ruta = $archivo->storeAs($personal->id, $nombreArchivo, 'personal_vacaciones');

            $docData = [
                'documento_ruta'             => $ruta,
                'documento_nombre_original'  => $nombreOriginal,
                'documento_extension'        => $extension,
                'documento_tamanio_kb'       => $tamanioKb,
            ];
        }

        $vacacion = PersonalVacacion::create(array_merge([
            'personal_id'          => $personal->id,
            'anio'                 => $anio,
            'fecha_inicio'         => $data['fecha_inicio'] ?? null,
            'fecha_fin'            => $data['fecha_fin'] ?? null,
            'dias_solicitados'     => $data['dias_solicitados'],
            'dias_aprobados'       => $data['dias_aprobados'],
            'descripcion'          => $data['descripcion'] ?? null,
            'observaciones'        => $data['observaciones'] ?? null,
            'registrado_por_user_id' => auth()->id(),
        ], $docData));

        $vacacion->load('registradoPor:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Vacación registrada exitosamente.',
            'data'    => $this->formatVacacion($vacacion),
        ], 201);
    }

    /**
     * Actualizar un registro de vacación existente.
     */
    public function update(Request $request, Personal $personal, PersonalVacacion $vacacion): JsonResponse
    {
        if ($vacacion->personal_id !== $personal->id) {
            return response()->json([
                'success' => false,
                'message' => 'El registro no pertenece a este empleado.',
            ], 404);
        }

        $data = $request->validate([
            'fecha_inicio'     => 'nullable|date',
            'fecha_fin'        => 'nullable|date|after_or_equal:fecha_inicio',
            'dias_solicitados' => 'sometimes|integer|min:1',
            'dias_aprobados'   => 'sometimes|integer|min:0',
            'descripcion'      => 'nullable|string|max:1000',
            'observaciones'    => 'nullable|string|max:1000',
            'documento'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'eliminar_documento' => 'nullable|boolean',
        ]);

        // Recalcular anio si se actualiza fecha_inicio
        if (isset($data['fecha_inicio'])) {
            $data['anio'] = Carbon::parse($data['fecha_inicio'])->year;
        }

        if ($request->boolean('eliminar_documento') && $vacacion->documento_ruta) {
            Storage::disk('personal_vacaciones')->delete($vacacion->documento_ruta);
            $data['documento_ruta']            = null;
            $data['documento_nombre_original'] = null;
            $data['documento_extension']       = null;
            $data['documento_tamanio_kb']      = null;
        }

        if ($request->hasFile('documento')) {
            if ($vacacion->documento_ruta) {
                Storage::disk('personal_vacaciones')->delete($vacacion->documento_ruta);
            }
            $archivo        = $request->file('documento');
            $extension      = strtolower($archivo->getClientOriginalExtension());
            $nombreOriginal = $archivo->getClientOriginalName();
            $tamanioKb      = (int) ceil($archivo->getSize() / 1024);
            $nombreArchivo  = sprintf('%s_vacacion_%s.%s', $personal->id, now()->format('YmdHis'), $extension);

            $data['documento_ruta']            = $archivo->storeAs($personal->id, $nombreArchivo, 'personal_vacaciones');
            $data['documento_nombre_original'] = $nombreOriginal;
            $data['documento_extension']       = $extension;
            $data['documento_tamanio_kb']      = $tamanioKb;
        }

        unset($data['documento'], $data['eliminar_documento']);
        $vacacion->update($data);
        $vacacion->load('registradoPor:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Vacación actualizada exitosamente.',
            'data'    => $this->formatVacacion($vacacion),
        ]);
    }

    /**
     * Eliminar un registro de vacación.
     */
    public function destroy(Personal $personal, PersonalVacacion $vacacion): JsonResponse
    {
        if ($vacacion->personal_id !== $personal->id) {
            return response()->json([
                'success' => false,
                'message' => 'El registro no pertenece a este empleado.',
            ], 404);
        }

        if ($vacacion->documento_ruta && Storage::disk('personal_vacaciones')->exists($vacacion->documento_ruta)) {
            Storage::disk('personal_vacaciones')->delete($vacacion->documento_ruta);
        }

        $vacacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro de vacación eliminado.',
        ]);
    }

    /**
     * Descargar documento adjunto.
     */
    public function downloadDocumento(Personal $personal, PersonalVacacion $vacacion)
    {
        if ($vacacion->personal_id !== $personal->id) {
            return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
        }

        if (! $vacacion->documento_ruta || ! Storage::disk('personal_vacaciones')->exists($vacacion->documento_ruta)) {
            return response()->json(['success' => false, 'message' => 'El documento no existe.'], 404);
        }

        return Storage::disk('personal_vacaciones')->download(
            $vacacion->documento_ruta,
            $vacacion->documento_nombre_original
        );
    }

    /**
     * Actualizar el saldo inicial de días de un empleado.
     */
    public function updateSaldoInicial(Request $request, Personal $personal): JsonResponse
    {
        $data = $request->validate([
            'saldo_inicial' => 'required|integer|min:0|max:365',
        ]);

        $personal->update(['vacaciones_saldo_inicial' => $data['saldo_inicial']]);

        return response()->json([
            'success' => true,
            'message' => 'Saldo inicial actualizado.',
            'data'    => $this->calcularSaldo($personal->fresh()),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function calcularSaldo(Personal $personal): array
    {
        $diasAnuales  = VacacionConfig::diasParaDepartamento($personal->departamento_id);
        $diasTomados  = (int) $personal->vacaciones()->sum('dias_aprobados');
        $saldoInicial = (int) ($personal->vacaciones_saldo_inicial ?? 0);

        $aniosEnSistema = $this->calcularAniosEnSistema($personal);
        $diasAcumulados = $aniosEnSistema * $diasAnuales;

        $saldoDisponible = max(0, $saldoInicial + $diasAcumulados - $diasTomados);

        return [
            'saldo_inicial'    => $saldoInicial,
            'dias_anuales'     => $diasAnuales,
            'anios_en_sistema' => $aniosEnSistema,
            'dias_acumulados'  => $diasAcumulados,
            'dias_tomados'     => $diasTomados,
            'saldo_disponible' => $saldoDisponible,
        ];
    }

    /**
     * Cuenta cuántos aniversarios de fecha_inicio caen dentro del período de tracking
     * (desde anio_inicio_track-01-01 hasta hoy). Solo los aniversarios cumplidos en
     * el período de tracking generan días automáticos; los anteriores van en saldo_inicial.
     */
    private function calcularAniosEnSistema(Personal $personal): int
    {
        if (! $personal->fecha_inicio) {
            return 0;
        }

        $hoy           = now();
        $fechaInicio   = Carbon::parse($personal->fecha_inicio);
        $trackingStart = Carbon::create(
            (int) config('planilla.vacaciones_anio_inicio', 2026), 1, 1
        );

        // Años completos totales desde fecha_inicio hasta hoy
        $totalAnios = (int) $fechaInicio->diffInYears($hoy);

        if ($totalAnios === 0) {
            return 0;
        }

        if ($fechaInicio->gte($trackingStart)) {
            // Empleado entró después del inicio del tracking: todos sus aniversarios cuentan
            return $totalAnios;
        }

        // Empleado entró antes del tracking: solo cuentan los aniversarios >= trackingStart
        $k           = (int) $fechaInicio->diffInYears($trackingStart);
        $aniversarioK = $fechaInicio->copy()->addYears($k);
        // Primer N tal que fecha_inicio + N >= trackingStart
        $minN = $aniversarioK->lt($trackingStart) ? $k + 1 : $k;

        return max(0, $totalAnios - $minN + 1);
    }

    private function formatVacacion(PersonalVacacion $vacacion): array
    {
        $baseUrl = config('app.url');

        return [
            'id'               => $vacacion->id,
            'anio'             => $vacacion->anio,
            'fecha_inicio'     => $vacacion->fecha_inicio?->format('Y-m-d'),
            'fecha_fin'        => $vacacion->fecha_fin?->format('Y-m-d'),
            'dias_solicitados' => $vacacion->dias_solicitados,
            'dias_aprobados'   => $vacacion->dias_aprobados,
            'descripcion'      => $vacacion->descripcion,
            'observaciones'    => $vacacion->observaciones,
            'tiene_documento'  => $vacacion->tiene_documento,
            'documento_nombre' => $vacacion->documento_nombre_original,
            'documento_extension' => $vacacion->documento_extension,
            'documento_tamanio_kb' => $vacacion->documento_tamanio_kb,
            'url_documento'    => $vacacion->tiene_documento
                ? "{$baseUrl}/api/v1/personal/{$vacacion->personal_id}/vacaciones/{$vacacion->id}/documento"
                : null,
            'registrado_por'   => $vacacion->registradoPor ? [
                'id'   => $vacacion->registradoPor->id,
                'name' => $vacacion->registradoPor->name,
            ] : null,
            'created_at'       => $vacacion->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
