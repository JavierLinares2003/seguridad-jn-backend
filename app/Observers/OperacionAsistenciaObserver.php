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

            $ids = collect([
                $asistencia->getPersonalIdEfectivo(),
                $asistencia->personal_reemplazo_id,
            ])->filter()->unique()->values();

            if ($ids->isEmpty()) {
                return;
            }

            $planillas = Planilla::where('estado_planilla', 'borrador')
                ->where('periodo_inicio', '<=', $fechaAsistencia)
                ->where('periodo_fin', '>=', $fechaAsistencia)
                ->get();

            $recalculadas = [];
            foreach ($planillas as $planilla) {
                foreach ($ids as $personalId) {
                    if ($this->personalEstaEnAmbitoPlanilla((int) $personalId, $planilla)) {
                        if (isset($recalculadas[$planilla->id])) {
                            continue;
                        }
                        Log::info("Recalculando planilla {$planilla->id} por cambio en asistencia {$asistencia->id}");
                        $this->planillaService->recalcularPlanilla($planilla->id);
                        $recalculadas[$planilla->id] = true;
                    }
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
