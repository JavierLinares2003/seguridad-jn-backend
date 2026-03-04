<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operaciones\StoreTransaccionRequest;
use App\Models\Prestamo;
use App\Models\Transaccion;
use App\Services\PrestamoService;
use Illuminate\Http\Request;

class TransaccionController extends Controller
{
    /**
     * Display a listing of transactions.
     */
    public function index(Request $request)
    {
        $query = Transaccion::with(['personal', 'prestamo', 'asistencia', 'registradoPor']);

        // Filter by personal_id
        if ($request->has('personal_id')) {
            $query->where('personal_id', $request->personal_id);
        }

        // Filter by tipo_transaccion
        if ($request->has('tipo')) {
            $query->where('tipo_transaccion', $request->tipo);
        }

        // Filter by estado
        if ($request->has('estado')) {
            $query->where('estado_transaccion', $request->estado);
        }

        // Filter by date range
        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha_transaccion', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('fecha_transaccion', '<=', $request->fecha_hasta);
        }

        // Filter by prestamo_id
        if ($request->has('prestamo_id')) {
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
    public function store(StoreTransaccionRequest $request, PrestamoService $prestamoService)
    {
        $data = $request->validated();

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

        // Para otros tipos de transacciones, crear normalmente
        $transaccion = Transaccion::create($data);

        // Load relationships
        $transaccion->load(['personal', 'prestamo', 'asistencia', 'registradoPor']);

        return response()->json([
            'success' => true,
            'message' => 'Transacción creada exitosamente.',
            'data' => $transaccion,
        ], 201);
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

