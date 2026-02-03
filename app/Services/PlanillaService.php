<?php

namespace App\Services;

use App\Models\Planilla;
use App\Models\PlanillaDetalle;
use App\Models\Personal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PlanillaService
{
    /**
     * Generar nueva planilla para un período
     */
    public function generarPlanilla($periodoInicio, $periodoFin, $proyectoId = null, $observaciones = null)
    {
        // Verificar que no exista planilla para este período
        $existente = Planilla::porPeriodo($periodoInicio, $periodoFin)->first();
        
        if ($existente) {
            throw new \Exception("Ya existe una planilla para el período {$periodoInicio} - {$periodoFin}");
        }
        
        DB::beginTransaction();
        
        try {
            // Crear planilla
            $planilla = Planilla::create([
                'nombre_planilla' => "Planilla {$periodoInicio} - {$periodoFin}",
                'periodo_inicio' => $periodoInicio,
                'periodo_fin' => $periodoFin,
                'estado_planilla' => 'borrador',
                'creado_por_user_id' => Auth::id(),
                'observaciones' => $observaciones,
            ]);
            
            // Obtener personal activo
            $query = Personal::where('estado', 'activo');
            
            // Si se especifica proyecto, filtrar solo personal de ese proyecto
            if ($proyectoId) {
                $query->whereHas('asignaciones', function ($q) use ($proyectoId, $periodoFin) {
                    $q->where('proyecto_id', $proyectoId)
                      ->where('estado_asignacion', 'activa')
                      ->where(function ($q2) use ($periodoFin) {
                          $q2->whereNull('fecha_fin')
                             ->orWhere('fecha_fin', '>=', $periodoFin);
                      });
                });
            }
            
            $personal = $query->get();
            
            $totalDevengado = 0;
            $totalDescuentos = 0;
            $totalNeto = 0;
            
            // Generar detalle para cada empleado
            foreach ($personal as $empleado) {
                // Llamar función PostgreSQL para calcular planilla
                $resultado = DB::select(
                    'SELECT * FROM calcular_planilla_personal(?, ?, ?)',
                    [$empleado->id, $periodoInicio, $periodoFin]
                );
                
                if (empty($resultado)) {
                    continue;
                }
                
                $calc = $resultado[0];
                
                // Solo crear detalle si trabajó días o tiene descuentos
                if ($calc->dias_trabajados > 0 || $calc->total_descuentos > 0) {
                    $detalle = PlanillaDetalle::create([
                        'planilla_id' => $planilla->id,
                        'personal_id' => $calc->personal_id,
                        'proyecto_id' => $calc->proyecto_id,
                        'dias_trabajados' => $calc->dias_trabajados,
                        'horas_trabajadas' => $calc->horas_trabajadas,
                        'pago_por_hora' => $calc->pago_por_hora,
                        'salario_devengado' => $calc->salario_devengado,
                        'descuento_multas' => $calc->descuento_multas,
                        'descuento_uniformes' => $calc->descuento_uniformes,
                        'descuento_anticipos' => $calc->descuento_anticipos,
                        'descuento_prestamos' => $calc->descuento_prestamos,
                        'descuento_antecedentes' => $calc->descuento_antecedentes,
                        'otros_descuentos' => $calc->otros_descuentos,
                        'total_descuentos' => $calc->total_descuentos,
                        'salario_neto' => $calc->salario_neto,
                    ]);
                    
                    $totalDevengado += $detalle->salario_devengado;
                    $totalDescuentos += $detalle->total_descuentos;
                    $totalNeto += $detalle->salario_neto;
                }
            }
            
            // Actualizar totales de planilla
            $planilla->update([
                'total_devengado' => $totalDevengado,
                'total_descuentos' => $totalDescuentos,
                'total_neto' => $totalNeto,
            ]);
            
            DB::commit();
            
            // Cargar relaciones
            return $planilla->load([
                'detalles.personal',
                'detalles.proyecto',
                'creadoPor'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Aprobar planilla y marcar transacciones como aplicadas
     */
    public function aprobarPlanilla($planillaId)
    {
        DB::beginTransaction();
        
        try {
            $planilla = Planilla::with('detalles')->findOrFail($planillaId);
            
            if (!$planilla->puedeAprobarse()) {
                throw new \Exception('Solo se pueden aprobar planillas en estado borrador');
            }
            
            // Marcar todas las transacciones pendientes como aplicadas
            foreach ($planilla->detalles as $detalle) {
                DB::table('operaciones_transacciones')
                    ->where('personal_id', $detalle->personal_id)
                    ->whereBetween('fecha_transaccion', [
                        $planilla->periodo_inicio,
                        $planilla->periodo_fin
                    ])
                    ->where('estado_transaccion', 'pendiente')
                    ->where('es_descuento', true)
                    ->update([
                        'estado_transaccion' => 'aplicado',
                        'updated_at' => now()
                    ]);
            }
            
            // Aprobar planilla
            $planilla->update([
                'estado_planilla' => 'aprobada',
                'aprobado_por_user_id' => Auth::id(),
                'fecha_aprobacion' => now(),
            ]);
            
            DB::commit();
            
            return $planilla->load(['aprobadoPor']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Marcar planilla como pagada
     */
    public function marcarComoPagada($planillaId)
    {
        $planilla = Planilla::findOrFail($planillaId);
        
        if ($planilla->estado_planilla !== 'aprobada') {
            throw new \Exception('Solo se pueden marcar como pagadas las planillas aprobadas');
        }
        
        $planilla->update([
            'estado_planilla' => 'pagada',
        ]);
        
        return $planilla;
    }
    
    /**
     * Cancelar planilla (solo si está en borrador)
     */
    public function cancelarPlanilla($planillaId, $motivo = null)
    {
        $planilla = Planilla::findOrFail($planillaId);
        
        if (!$planilla->puedeEditarse()) {
            throw new \Exception('Solo se pueden cancelar planillas en borrador o revisión');
        }
        
        $planilla->update([
            'estado_planilla' => 'cancelada',
            'observaciones' => $motivo ? 
                ($planilla->observaciones ? $planilla->observaciones . "\n\nMotivo cancelación: " . $motivo : "Motivo cancelación: " . $motivo) :
                $planilla->observaciones,
        ]);
        
        return $planilla;
    }
    
    /**
     * Recalcular totales de planilla
     */
    public function recalcularTotales($planillaId)
    {
        $planilla = Planilla::with('detalles')->findOrFail($planillaId);
        
        if (!$planilla->puedeEditarse()) {
            throw new \Exception('Solo se pueden recalcular planillas en borrador o revisión');
        }
        
        $totalDevengado = $planilla->detalles->sum('salario_devengado');
        $totalDescuentos = $planilla->detalles->sum('total_descuentos');
        $totalNeto = $planilla->detalles->sum('salario_neto');
        
        $planilla->update([
            'total_devengado' => $totalDevengado,
            'total_descuentos' => $totalDescuentos,
            'total_neto' => $totalNeto,
        ]);
        
        return $planilla;
    }
}
