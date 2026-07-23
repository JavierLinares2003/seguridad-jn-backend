<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operaciones\StorePrestamoRequest;
use App\Models\Prestamo;
use App\Services\PrestamoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrestamoController extends Controller
{
    /**
     * Display a listing of loans.
     */
    public function index(Request $request)
    {
        $query = Prestamo::with(['personal', 'aprobadoPor']);

        // Filter by personal_id
        if ($request->filled('personal_id')) {
            $query->where('personal_id', $request->personal_id);
        }

        // Filter by estado
        if ($request->filled('estado')) {
            $query->where('estado_prestamo', $request->estado);
        }

        // Filter by date range
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_prestamo', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_prestamo', '<=', $request->fecha_hasta);
        }

        // Order by most recent
        $query->orderBy('fecha_prestamo', 'desc');

        $prestamos = $query->get();

        return response()->json([
            'success' => true,
            'data' => $prestamos,
        ]);
    }

    /**
     * Store a newly created loan.
     */
    public function store(StorePrestamoRequest $request, PrestamoService $prestamoService)
    {
        $data = $request->validated();
        unset($data['comprobante']);

        // Set saldo_pendiente equal to monto_total initially
        $data['saldo_pendiente'] = $data['monto_total'];

        // Set aprobado_por_user_id to current user
        $data['aprobado_por_user_id'] = auth()->id();

        $prestamo = Prestamo::create($data);
        $this->guardarComprobante($request, $prestamo, 'prestamos');

        // Generar cuotas automáticas si tiene cuotas definidas
        $cuotasGeneradas = 0;
        if ($prestamo->cuotas_totales > 0 && $prestamo->monto_cuota > 0) {
            $cuotasGeneradas = $prestamoService->generarCuotasAutomaticas($prestamo, auth()->id());
        }

        // Load relationships
        $prestamo->load(['personal', 'aprobadoPor']);

        return response()->json([
            'success' => true,
            'message' => 'Préstamo creado exitosamente.' . ($cuotasGeneradas > 0 ? " Se generaron {$cuotasGeneradas} cuotas automáticas." : ''),
            'data' => $prestamo,
            'cuotas_generadas' => $cuotasGeneradas,
        ], 201);
    }

    /**
     * Display the specified loan.
     */
    public function show($id)
    {
        $prestamo = Prestamo::with(['personal', 'aprobadoPor', 'transacciones'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $prestamo,
        ]);
    }

    /**
     * Cancel a loan.
     */
    public function cancelar($id)
    {
        $prestamo = Prestamo::findOrFail($id);

        if ($prestamo->estado_prestamo !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden cancelar préstamos activos.',
            ], 422);
        }

        $prestamo->update([
            'estado_prestamo' => 'cancelado',
        ]);

        $prestamo->load(['personal', 'aprobadoPor']);

        return response()->json([
            'success' => true,
            'message' => 'Préstamo cancelado exitosamente.',
            'data' => $prestamo,
        ]);
    }

    /**
     * Eliminar permanentemente un préstamo (solo admin, confirmación fuerte).
     * POST /api/v1/operaciones/prestamos/{id}/eliminar
     * Body: { confirmacion: "ELIMINAR" }
     *
     * Borra cuotas/abonos relacionados, comprobantes y el préstamo.
     */
    public function eliminar($id, Request $request)
    {
        if (!auth()->user()?->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo un administrador puede eliminar préstamos.',
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

        $prestamo = Prestamo::with('transacciones')->findOrFail($id);

        DB::transaction(function () use ($prestamo) {
            foreach ($prestamo->transacciones as $transaccion) {
                if ($transaccion->comprobante_ruta
                    && Storage::disk('operaciones_comprobantes')->exists($transaccion->comprobante_ruta)
                ) {
                    Storage::disk('operaciones_comprobantes')->delete($transaccion->comprobante_ruta);
                }
                $transaccion->delete();
            }

            if ($prestamo->comprobante_ruta
                && Storage::disk('operaciones_comprobantes')->exists($prestamo->comprobante_ruta)
            ) {
                Storage::disk('operaciones_comprobantes')->delete($prestamo->comprobante_ruta);
            }

            $prestamo->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Préstamo eliminado permanentemente.',
        ]);
    }

    /**
     * Get payment history of a loan.
     */
    public function historial($id)
    {
        $prestamo = Prestamo::with(['personal', 'aprobadoPor'])->findOrFail($id);

        $transacciones = $prestamo->transacciones()
            ->with(['registradoPor'])
            ->where('tipo_transaccion', 'abono_prestamo')
            ->orderBy('fecha_transaccion', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'prestamo' => $prestamo,
                'transacciones' => $transacciones,
            ],
        ]);
    }

    /**
     * GET /api/v1/prestamos/{id}/resumen
     * Obtiene un resumen detallado del estado del préstamo.
     */
    public function resumen($id, PrestamoService $prestamoService)
    {
        $prestamo = Prestamo::findOrFail($id);
        $resumen = $prestamoService->obtenerResumenPrestamo($prestamo);

        return response()->json([
            'success' => true,
            'data' => $resumen,
        ]);
    }

    /**
     * Return the comprobante file of a loan (inline preview).
     */
    public function comprobante($id)
    {
        $prestamo = Prestamo::findOrFail($id);

        if (!$prestamo->comprobante_ruta) {
            return response()->json([
                'success' => false,
                'message' => 'Este préstamo no tiene comprobante adjunto.',
            ], 404);
        }

        if (!Storage::disk('operaciones_comprobantes')->exists($prestamo->comprobante_ruta)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo de comprobante no fue encontrado.',
            ], 404);
        }

        $contenido = Storage::disk('operaciones_comprobantes')->get($prestamo->comprobante_ruta);
        $mimeType = Storage::disk('operaciones_comprobantes')->mimeType($prestamo->comprobante_ruta);

        return response($contenido, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $prestamo->comprobante_nombre_original . '"');
    }

    /**
     * Delete the comprobante file of a loan.
     */
    public function deleteComprobante($id)
    {
        $prestamo = Prestamo::findOrFail($id);

        if (!$prestamo->comprobante_ruta) {
            return response()->json([
                'success' => false,
                'message' => 'Este préstamo no tiene comprobante adjunto.',
            ], 404);
        }

        if (Storage::disk('operaciones_comprobantes')->exists($prestamo->comprobante_ruta)) {
            Storage::disk('operaciones_comprobantes')->delete($prestamo->comprobante_ruta);
        }

        $prestamo->update([
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
}

