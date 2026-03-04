<?php

namespace App\Observers;

use App\Models\OperacionAsistencia;
use App\Models\Planilla;
use App\Services\PlanillaService;
use Illuminate\Support\Facades\Log;

class OperacionAsistenciaObserver
{
    protected $planillaService;

    public function __construct(PlanillaService $planillaService)
    {
        $this->planillaService = $planillaService;
    }

    /**
     * Handle the OperacionAsistencia "created" event.
     */
    public function created(OperacionAsistencia $asistencia): void
    {
        $this->recalcularPlanillasAfectadas($asistencia);
    }

    /**
     * Handle the OperacionAsistencia "updated" event.
     */
    public function updated(OperacionAsistencia $asistencia): void
    {
        $this->recalcularPlanillasAfectadas($asistencia);
    }

    /**
     * Handle the OperacionAsistencia "deleted" event.
     */
    public function deleted(OperacionAsistencia $asistencia): void
    {
        $this->recalcularPlanillasAfectadas($asistencia);
    }

    /**
     * Recalcula planillas en borrador afectadas por el cambio en la asistencia
     */
    private function recalcularPlanillasAfectadas(OperacionAsistencia $asistencia): void
    {
        try {
            // Obtener la fecha de la asistencia
            $fechaAsistencia = $asistencia->fecha_asistencia;

            // Obtener el personal_id (puede venir de personal_id directo o de la asignación)
            $personalId = $asistencia->personal_id;

            if (!$personalId && $asistencia->personalAsignado) {
                $personalId = $asistencia->personalAsignado->personal_id;
            }

            if (!$personalId) {
                return; // No hay personal asociado
            }

            // Buscar planillas en borrador que incluyan esta fecha
            $planillas = Planilla::where('estado_planilla', 'borrador')
                ->where('periodo_inicio', '<=', $fechaAsistencia)
                ->where('periodo_fin', '>=', $fechaAsistencia)
                ->get();

            foreach ($planillas as $planilla) {
                // Verificar si el personal está en el ámbito de la planilla
                if ($this->personalEstaEnAmbitoPlanilla($personalId, $planilla)) {
                    Log::info("Recalculando planilla {$planilla->id} por cambio en asistencia {$asistencia->id}");
                    $this->planillaService->recalcularPlanilla($planilla->id);
                }
            }
        } catch (\Exception $e) {
            // Registrar el error pero no lanzar excepción para no interrumpir el flujo
            Log::error("Error al recalcular planillas después de cambio en asistencia: " . $e->getMessage());
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
