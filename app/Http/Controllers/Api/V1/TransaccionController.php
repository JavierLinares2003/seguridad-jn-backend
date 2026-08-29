<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operaciones\StoreTransaccionRequest;
use App\Models\Prestamo;
use App\Models\ProyectoInventario;
use App\Models\Transaccion;
use App\Services\PrestamoService;
use App\Services\UniformeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class TransaccionController extends Controller
{
    /**
     * Display a listing of transactions.
     */
    public function index(Request $request)
    {
        $query = Transaccion::with(['personal', 'prestamo', 'asistencia', 'registradoPor']);

        // Filter by personal_id
        if ($request->filled('personal_id')) {
            $query->where('personal_id', $request->personal_id);
        }

        // Filter by tipo_transaccion
        if ($request->filled('tipo')) {
            $query->where('tipo_transaccion', $request->tipo);
        }

        // Filter by estado
        if ($request->filled('estado')) {
            $query->where('estado_transaccion', $request->estado);
        }

        // Filter by date range
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_transaccion', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_transaccion', '<=', $request->fecha_hasta);
        }

        // Filter by prestamo_id
        if ($request->filled('prestamo_id')) {
            $query->where('prestamo_id', $request->prestamo_id);
        }

        // Order by most recent
        $query->orderBy('fecha_transaccion', 'desc');

        $transacciones = $query->get();

        return response()->json([
            'success' => true,
            'data' => $transacciones,
        ]);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(StoreTransaccionRequest $request, PrestamoService $prestamoService, UniformeService $uniformeService)
    {
        $data = $request->validated();
        unset($data['comprobante']);

        // Set registrado_por_user_id to current user
        $data['registrado_por_user_id'] = auth()->id();

        // Si es un abono a préstamo, usar el servicio de préstamos para manejar la lógica
        if ($data['tipo_transaccion'] === 'abono_prestamo' && isset($data['prestamo_id'])) {
            try {
                $prestamo = Prestamo::findOrFail($data['prestamo_id']);

                $resultado = $prestamoService->procesarAbono(
                    $prestamo,
                    $data['monto'],
                    $data['descripcion'],
                    auth()->id()
                );

                $transaccion = $resultado['transaccion'];
                $this->guardarComprobante($request, $transaccion, 'transacciones');
                $transaccion->load(['personal', 'prestamo', 'asistencia', 'registradoPor']);

                $mensaje = 'Abono realizado exitosamente.';

                if ($resultado['tipo_abono'] === 'liquidacion_total') {
                    $mensaje = '¡Préstamo liquidado completamente! Se cancelaron las cuotas restantes.';
                } elseif ($resultado['tipo_abono'] === 'abono_extra') {
                    $mensaje = 'Abono extra realizado. Se recalcularon las cuotas restantes.';
                }

                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'data' => $transaccion,
                    'info_prestamo' => [
                        'tipo_abono' => $resultado['tipo_abono'],
                        'cuotas_recalculadas' => $resultado['cuotas_recalculadas'],
                        'saldo_pendiente' => $prestamo->fresh()->saldo_pendiente,
                    ],
                ], 201);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el abono: ' . $e->getMessage(),
                ], 422);
            }
        }

        // Descuento de uniforme con cuotas quincenales
        $tieneCuotas = $data['tipo_transaccion'] === 'uniforme'
            && (
                (isset($data['cuotas']) && is_array($data['cuotas']) && count($data['cuotas']) > 0)
                || (isset($data['cuotas_totales']) && (int) $data['cuotas_totales'] >= 1)
            );

        if ($tieneCuotas) {
            try {
                $resultado = $uniformeService->crearDescuentoUniforme([
                    'personal_id' => $data['personal_id'],
                    'asistencia_id' => $data['asistencia_id'] ?? null,
                    'monto' => $data['monto'],
                    'descripcion' => $data['descripcion'],
                    'fecha_inicio' => $data['fecha_inicio'] ?? $data['fecha_transaccion'] ?? now()->toDateString(),
                    'fecha_transaccion' => $data['fecha_transaccion'] ?? null,
                    'cuotas_totales' => (int) ($data['cuotas_totales'] ?? 1),
                    'cuotas' => $data['cuotas'] ?? null,
                    'registrado_por_user_id' => $data['registrado_por_user_id'],
                ]);

                $primera = $resultado['transacciones']->first();
                if ($primera) {
                    $this->guardarComprobante($request, $primera, 'transacciones');
                    $primera->load(['personal', 'prestamo', 'asistencia', 'registradoPor']);
                }

                $resultado['transacciones']->each->load(['personal', 'prestamo', 'asistencia', 'registradoPor']);

                return response()->json([
                    'success' => true,
                    'message' => 'Descuento de uniforme registrado con '
                        . $resultado['transacciones']->count() . ' cuota(s) quincenal(es).',
                    'data' => $primera,
                    'grupo_uniforme' => $resultado['grupo_uniforme'],
                    'cuotas' => $resultado['transacciones']->values(),
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al registrar el descuento de uniforme: ' . $e->getMessage(),
                ], 422);
            }
        }

        // Para otros tipos de transacciones, crear normalmente
        unset($data['cuotas'], $data['cuotas_totales'], $data['fecha_inicio']);
        $transaccion = Transaccion::create($data);
        $this->guardarComprobante($request, $transaccion, 'transacciones');

        // Load relationships
        $transaccion->load(['personal', 'prestamo', 'asistencia', 'registradoPor']);

        return response()->json([
            'success' => true,
            'message' => 'Transacción creada exitosamente.',
            'data' => $transaccion,
        ], 201);
    }

    /**
     * Actualizar fecha de una cuota pendiente de uniforme.
     * PUT /api/v1/operaciones/transacciones/{id}/fecha
     */
    public function actualizarFecha($id, Request $request, UniformeService $uniformeService)
    {
        $request->validate([
            'fecha_transaccion' => ['required', 'date'],
        ], [
            'fecha_transaccion.required' => 'La fecha es requerida.',
            'fecha_transaccion.date' => 'La fecha no es válida.',
        ]);

        $transaccion = Transaccion::findOrFail($id);

        try {
            $actualizada = $uniformeService->actualizarFechaCuota(
                $transaccion,
                $request->input('fecha_transaccion')
            );

            return response()->json([
                'success' => true,
                'message' => 'Fecha de cuota actualizada.',
                'data' => $actualizada,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Desglose de cuotas de un grupo de uniforme.
     * GET /api/v1/operaciones/transacciones/grupo-uniforme/{grupo}
     */
    public function desgloseGrupoUniforme($grupo, UniformeService $uniformeService)
    {
        $cuotas = $uniformeService->obtenerDesgloseGrupo($grupo);

        if ($cuotas->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el grupo de uniforme.',
            ], 404);
        }

        $pendiente = $cuotas->where('estado_transaccion', 'pendiente')->sum('monto');
        $aplicado = $cuotas->where('estado_transaccion', 'aplicado')->sum('monto');

        return response()->json([
            'success' => true,
            'data' => [
                'grupo_uniforme' => $grupo,
                'monto_total' => (float) ($cuotas->first()->monto_total_uniforme ?? $cuotas->sum('monto')),
                'saldo_pendiente' => (float) $pendiente,
                'monto_aplicado' => (float) $aplicado,
                'cuotas_totales' => (int) ($cuotas->first()->cuotas_totales ?? $cuotas->count()),
                'cuotas_aplicadas' => $cuotas->where('estado_transaccion', 'aplicado')->count(),
                'cuotas_pendientes' => $cuotas->where('estado_transaccion', 'pendiente')->count(),
                'cuotas' => $cuotas,
            ],
        ]);
    }

    /**
     * Listar planes de uniforme de un personal.
     * GET /api/v1/operaciones/transacciones/uniformes?personal_id=
     */
    public function listarUniformes(Request $request, UniformeService $uniformeService)
    {
        $request->validate([
            'personal_id' => ['required', 'exists:personal,id'],
        ]);

        $grupos = $uniformeService->listarGruposPorPersonal((int) $request->personal_id);

        return response()->json([
            'success' => true,
            'data' => $grupos,
        ]);
    }

    /**
     * Actualizar fechas de cuotas pendientes (lote).
     * PUT /api/v1/operaciones/transacciones/grupo-uniforme/{grupo}/fechas
     */
    public function actualizarFechasGrupo($grupo, Request $request, UniformeService $uniformeService)
    {
        $request->validate([
            'cuotas' => ['required', 'array', 'min:1'],
            'cuotas.*.id' => ['required', 'integer', 'exists:operaciones_transacciones,id'],
            'cuotas.*.fecha_transaccion' => ['nullable', 'date'],
            'cuotas.*.monto' => ['nullable', 'numeric', 'min:0.01'],
        ], [
            'cuotas.required' => 'Debe enviar las cuotas a actualizar.',
        ]);

        try {
            $actualizadas = $uniformeService->actualizarFechasPendientes(
                $grupo,
                $request->input('cuotas')
            );

            $cuotas = $uniformeService->obtenerDesgloseGrupo($grupo);

            return response()->json([
                'success' => true,
                'message' => 'Se actualizaron ' . $actualizadas->count() . ' cuota(s) pendiente(s).',
                'data' => [
                    'actualizadas' => $actualizadas->count(),
                    'cuotas' => $cuotas,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reprogramar cuotas pendientes desde una fecha (quincenal).
     * PUT /api/v1/operaciones/transacciones/grupo-uniforme/{grupo}/reprogramar
     */
    public function reprogramarGrupoUniforme($grupo, Request $request, UniformeService $uniformeService)
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
        ], [
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_inicio.date' => 'La fecha de inicio no es válida.',
        ]);

        try {
            $actualizadas = $uniformeService->reprogramarFechasPendientes(
                $grupo,
                $request->input('fecha_inicio')
            );

            $cuotas = $uniformeService->obtenerDesgloseGrupo($grupo);

            return response()->json([
                'success' => true,
                'message' => 'Se reprogramaron ' . $actualizadas->count() . ' cuota(s) pendiente(s).',
                'data' => [
                    'actualizadas' => $actualizadas->count(),
                    'cuotas' => $cuotas,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cambiar el número total de cuotas del plan de uniforme.
     * PUT /api/v1/operaciones/transacciones/grupo-uniforme/{grupo}/cuotas
     */
    public function cambiarCuotasGrupoUniforme($grupo, Request $request, UniformeService $uniformeService)
    {
        $request->validate([
            'cuotas_totales' => ['required', 'integer', 'min:1', 'max:60'],
            'fecha_inicio' => ['nullable', 'date'],
        ], [
            'cuotas_totales.required' => 'Indique el nuevo número de cuotas.',
            'cuotas_totales.min' => 'Debe haber al menos 1 cuota.',
            'cuotas_totales.max' => 'El máximo de cuotas es 60.',
        ]);

        try {
            $cuotas = $uniformeService->cambiarNumeroCuotas(
                $grupo,
                (int) $request->input('cuotas_totales'),
                $request->input('fecha_inicio'),
                auth()->id()
            );

            $pendientes = $cuotas->where('estado_transaccion', 'pendiente');
            $aplicadas = $cuotas->where('estado_transaccion', 'aplicado');

            return response()->json([
                'success' => true,
                'message' => 'Número de cuotas actualizado. El saldo pendiente se redistribuyó.',
                'data' => [
                    'grupo_uniforme' => $grupo,
                    'monto_total' => (float) ($cuotas->first()->monto_total_uniforme ?? $cuotas->sum('monto')),
                    'saldo_pendiente' => (float) $pendientes->sum('monto'),
                    'monto_aplicado' => (float) $aplicadas->sum('monto'),
                    'cuotas_totales' => (int) ($cuotas->first()->cuotas_totales ?? $cuotas->count()),
                    'cuotas_aplicadas' => $aplicadas->count(),
                    'cuotas_pendientes' => $pendientes->count(),
                    'cuotas' => $cuotas,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show($id)
    {
        $transaccion = Transaccion::with(['personal', 'prestamo', 'asistencia', 'registradoPor'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $transaccion,
        ]);
    }

    /**
     * Cancel a pending transaction.
     */
    public function cancelar($id)
    {
        $transaccion = Transaccion::findOrFail($id);

        if ($transaccion->estado_transaccion !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden cancelar transacciones pendientes.',
            ], 422);
        }

        $transaccion->update([
            'estado_transaccion' => 'cancelado',
        ]);

        $transaccion->load(['personal', 'prestamo', 'asistencia', 'registradoPor']);

        return response()->json([
            'success' => true,
            'message' => 'Transacción cancelada exitosamente.',
            'data' => $transaccion,
        ]);
    }

    /**
     * Eliminar permanentemente una transacción (solo admin, confirmación fuerte).
     * POST /api/v1/operaciones/transacciones/{id}/eliminar
     * Body: { confirmacion: "ELIMINAR" }
     *
     * Limpieza asociada:
     * - comprobante en storage
     * - ítem de inventario creado desde la transacción
     * - si era abono aplicado, restablece saldo/cuotas del préstamo
     */
    public function eliminar($id, Request $request)
    {
        if (!auth()->user()?->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo un administrador puede eliminar transacciones.',
            ], 403);
        }

        $confirmacion = strtoupper(trim((string) $request->input('confirmacion', '')));
        if ($confirmacion !== 'ELIMINAR') {
            return response()->json([
                'success' => false,
                'message' => 'Confirmación inválida.',
                'errors' => ['confirmacion' => ['La confirmación no es válida.']],
            ], 422);
        }

        $transaccion = Transaccion::findOrFail($id);

        DB::transaction(function () use ($transaccion) {
            $this->revertirEfectosAntesDeEliminar($transaccion);
            $this->eliminarComprobanteArchivo($transaccion);
            $this->eliminarInventarioVinculado($transaccion);
            $transaccion->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Transacción eliminada permanentemente.',
        ]);
    }

    /**
     * Revierte efectos de negocio antes del hard-delete.
     */
    private function revertirEfectosAntesDeEliminar(Transaccion $transaccion): void
    {
        if (
            $transaccion->tipo_transaccion !== 'abono_prestamo'
            || $transaccion->estado_transaccion !== 'aplicado'
            || !$transaccion->prestamo_id
        ) {
            return;
        }

        $prestamo = Prestamo::lockForUpdate()->find($transaccion->prestamo_id);
        if (!$prestamo) {
            return;
        }

        $nuevoSaldo = min(
            (float) $prestamo->monto_total,
            (float) $prestamo->saldo_pendiente + (float) $transaccion->monto
        );

        $prestamo->update([
            'saldo_pendiente' => $nuevoSaldo,
            'cuotas_pagadas' => max(0, (int) $prestamo->cuotas_pagadas - 1),
            'estado_prestamo' => in_array($prestamo->estado_prestamo, ['pagado', 'cancelado'], true)
                ? 'activo'
                : $prestamo->estado_prestamo,
        ]);
    }

    private function eliminarComprobanteArchivo(Transaccion $transaccion): void
    {
        if (!$transaccion->comprobante_ruta) {
            return;
        }

        if (Storage::disk('operaciones_comprobantes')->exists($transaccion->comprobante_ruta)) {
            Storage::disk('operaciones_comprobantes')->delete($transaccion->comprobante_ruta);
        }
    }

    private function eliminarInventarioVinculado(Transaccion $transaccion): void
    {
        if (!Schema::hasColumn('operaciones_transacciones', 'proyecto_inventario_id')) {
            return;
        }

        if (!$transaccion->proyecto_inventario_id) {
            return;
        }

        $item = ProyectoInventario::find($transaccion->proyecto_inventario_id);
        $transaccion->update(['proyecto_inventario_id' => null]);

        if (!$item) {
            return;
        }

        $creadoDesdeEsta = str_contains(
            (string) $item->observaciones,
            'Creado desde transacción #' . $transaccion->id
        );

        if ($creadoDesdeEsta) {
            $item->delete();
        }
    }

    /**
     * Return the comprobante file of a transaction (inline preview).
     */
    public function comprobante($id)
    {
        $transaccion = Transaccion::findOrFail($id);

        if (!$transaccion->comprobante_ruta) {
            return response()->json([
                'success' => false,
                'message' => 'Esta transacción no tiene comprobante adjunto.',
            ], 404);
        }

        if (!Storage::disk('operaciones_comprobantes')->exists($transaccion->comprobante_ruta)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo de imagen no fue encontrado.',
            ], 404);
        }

        $contenido = Storage::disk('operaciones_comprobantes')->get($transaccion->comprobante_ruta);
        $mimeType = Storage::disk('operaciones_comprobantes')->mimeType($transaccion->comprobante_ruta);

        return response($contenido, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $transaccion->comprobante_nombre_original . '"');
    }

    /**
     * Delete the comprobante file of a transaction.
     */
    public function deleteComprobante($id)
    {
        $transaccion = Transaccion::findOrFail($id);

        if (!$transaccion->comprobante_ruta) {
            return response()->json([
                'success' => false,
                'message' => 'Esta transacción no tiene comprobante adjunto.',
            ], 404);
        }

        if (Storage::disk('operaciones_comprobantes')->exists($transaccion->comprobante_ruta)) {
            Storage::disk('operaciones_comprobantes')->delete($transaccion->comprobante_ruta);
        }

        $transaccion->update([
            'comprobante_ruta' => null,
            'comprobante_nombre_original' => null,
            'comprobante_extension' => null,
            'comprobante_tamanio_kb' => null,
            'comprobante_subido_por_user_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comprobante eliminado exitosamente.',
        ]);
    }

    /**
     * Store an uploaded comprobante file on the given model.
     */
    private function guardarComprobante(Request $request, $model, string $tipo): void
    {
        if (!$request->hasFile('comprobante')) {
            return;
        }

        $archivo = $request->file('comprobante');
        $extension = strtolower($archivo->getClientOriginalExtension());
        $nombreOriginal = $archivo->getClientOriginalName();
        $tamanioKb = (int) ceil($archivo->getSize() / 1024);

        $nombreArchivo = sprintf('%s_%s_%s.%s', $tipo, $model->id, now()->format('YmdHis'), $extension);

        $ruta = $archivo->storeAs($tipo . '/' . $model->id, $nombreArchivo, 'operaciones_comprobantes');

        $model->update([
            'comprobante_ruta' => $ruta,
            'comprobante_nombre_original' => $nombreOriginal,
            'comprobante_extension' => $extension,
            'comprobante_tamanio_kb' => $tamanioKb,
            'comprobante_subido_por_user_id' => auth()->id(),
        ]);
    }

    /**
     * Mark a transaction as applied.
     */
    public function aplicar($id)
    {
        $transaccion = Transaccion::findOrFail($id);

        if ($transaccion->estado_transaccion !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden aplicar transacciones pendientes.',
            ], 422);
        }

        $transaccion->update([
            'estado_transaccion' => 'aplicado',
        ]);

        $transaccion->load(['personal', 'prestamo', 'asistencia', 'registradoPor']);

        return response()->json([
            'success' => true,
            'message' => 'Transacción aplicada exitosamente.',
            'data' => $transaccion,
        ]);
    }
}

