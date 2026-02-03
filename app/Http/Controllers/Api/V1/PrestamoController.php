<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operaciones\StorePrestamoRequest;
use App\Models\Prestamo;
use Illuminate\Http\Request;

class PrestamoController extends Controller
{
    /**
     * Display a listing of loans.
     */
    public function index(Request $request)
    {
        $query = Prestamo::with(['personal', 'aprobadoPor']);

        // Filter by personal_id
        if ($request->has('personal_id')) {
            $query->where('personal_id', $request->personal_id);
        }

        // Filter by estado
        if ($request->has('estado')) {
            $query->where('estado_prestamo', $request->estado);
        }

        // Filter by date range
        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha_prestamo', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
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
    public function store(StorePrestamoRequest $request)
    {
        $data = $request->validated();

        // Set saldo_pendiente equal to monto_total initially
        $data['saldo_pendiente'] = $data['monto_total'];

        // Set aprobado_por_user_id to current user
        $data['aprobado_por_user_id'] = auth()->id();

        $prestamo = Prestamo::create($data);

        // Load relationships
        $prestamo->load(['personal', 'aprobadoPor']);

        return response()->json([
            'success' => true,
            'message' => 'Préstamo creado exitosamente.',
            'data' => $prestamo,
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
}

