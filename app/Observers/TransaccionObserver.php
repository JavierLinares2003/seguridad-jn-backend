<?php

namespace App\Observers;

use App\Models\Transaccion;
use App\Models\Planilla;
use App\Services\PlanillaService;
use Illuminate\Support\Facades\Log;

class TransaccionObserver
{
    protected $planillaService;

    public function __construct(PlanillaService $planillaService)
    {
        $this->planillaService = $planillaService;
    }

    /**
     * Handle the Transaccion "created" event.
     */
    public function created(Transaccion $transaccion): void
    {
        $this->recalcularPlanillasAfectadas($transaccion);
    }

    /**
     * Handle the Transaccion "updated" event.
     */
    public function updated(Transaccion $transaccion): void
    {
        $this->recalcularPlanillasAfectadas($transaccion);
    }

    /**
     * Handle the Transaccion "deleted" event.
     */
    public function deleted(Transaccion $transaccion): void
    {
        $this->recalcularPlanillasAfectadas($transaccion);
    }

    /**
     * Recalcula planillas en borrador afectadas por el cambio en la transacción
     */
    private function recalcularPlanillasAfectadas(Transaccion $transaccion): void
    {
        try {
            // Solo procesar transacciones que son descuentos
            if (!$transaccion->es_descuento) {
                return;
            }

            // Obtener la fecha de la transacción
            $fechaTransaccion = $transaccion->fecha_transaccion;
            $personalId = $transaccion->personal_id;

            if (!$personalId) {
                return;
            }

            // Buscar planillas en borrador que incluyan esta fecha
            $planillas = Planilla::where('estado_planilla', 'borrador')
                ->where('periodo_inicio', '<=', $fechaTransaccion)
                ->where('periodo_fin', '>=', $fechaTransaccion)
                ->get();

            foreach ($planillas as $planilla) {
                // Verificar si el personal está en el ámbito de la planilla
                if ($this->personalEstaEnAmbitoPlanilla($personalId, $planilla)) {
                    Log::info("Recalculando planilla {$planilla->id} por cambio en transacción {$transaccion->id}");
                    $this->planillaService->recalcularPlanilla($planilla->id);
                }
            }
        } catch (\Exception $e) {
            // Registrar el error pero no lanzar excepción para no interrumpir el flujo
            Log::error("Error al recalcular planillas después de cambio en transacción: " . $e->getMessage());
        }
    }

    /**
     * Verifica si el personal está en el ámbito de la planilla
     */
    private function personalEstaEnAmbitoPlanilla(int $personalId, Planilla $planilla): bool
    {
        $personal = \App\Models\Personal::find($personalId);

        if (!$personal) {
            return false;
        }

        // Si la planilla tiene proyecto específico
        if ($planilla->proyecto_id) {
            $tieneAsignacion = $personal->asignaciones()
                ->where('proyecto_id', $planilla->proyecto_id)
                ->where('estado_asignacion', 'activa')
                ->where(function ($q) use ($planilla) {
                    $q->whereNull('fecha_fin')
                      ->orWhere('fecha_fin', '>=', $planilla->periodo_fin);
                })
                ->exists();

            if (!$tieneAsignacion) {
                return false;
            }
        }

        // Si la planilla tiene departamento específico
        if ($planilla->departamento_id) {
            if ($personal->departamento_id !== $planilla->departamento_id) {
                return false;
            }
        }

        return true;
    }
}
