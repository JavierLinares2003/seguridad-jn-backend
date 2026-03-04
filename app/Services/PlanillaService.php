<?php

namespace App\Services;

use App\Models\Planilla;
use App\Models\PlanillaDetalle;
use App\Models\Personal;
use App\Models\Transaccion;
use App\Services\Planilla\TurnoHelper;
use App\Services\Planilla\Strategies\PlanillaCalculoStrategyFactory;
use App\Services\Planilla\Strategies\PlanillaCalculoStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PlanillaService
{
    /**
     * Generar nueva planilla para un período.
     *
     * @param string $periodoInicio Fecha de inicio del período
     * @param string $periodoFin Fecha de fin del período
     * @param int|null $proyectoId Filtrar por proyecto específico (opcional)
     * @param string|null $observaciones Observaciones de la planilla
     * @param string|null $tipoCalculo Estrategia de cálculo (default desde config)
     * @param int|null $departamentoId Filtrar por departamento específico (opcional)
     */
    public function generarPlanilla(
        $periodoInicio,
        $periodoFin,
        $proyectoId = null,
        $observaciones = null,
        $tipoCalculo = null,
        $departamentoId = null
    ) {
        // Obtener tipo de cálculo (parámetro > config > default)
        $tipoCalculo = $tipoCalculo ?? config('planilla.tipo_calculo_default', 'caso_1');

        // Obtener la estrategia de cálculo
        $strategy = PlanillaCalculoStrategyFactory::make($tipoCalculo);

        // Validar que no existan planillas activas para el mismo período y ámbito
        $this->validarPlanillaExistente($periodoInicio, $periodoFin, $proyectoId, $departamentoId);

        // Validar traslape con planillas pagadas
        $this->validarDiasPagados($periodoInicio, $periodoFin, $proyectoId, $departamentoId);

        DB::beginTransaction();

        try {
            // Generar nombre descriptivo según el ámbito
            $nombrePlanilla = $this->generarNombrePlanilla($periodoInicio, $periodoFin, $proyectoId, $departamentoId);

            // Crear planilla
            $planilla = Planilla::create([
                'nombre_planilla' => $nombrePlanilla,
                'periodo_inicio' => $periodoInicio,
                'periodo_fin' => $periodoFin,
                'estado_planilla' => 'borrador',
                'creado_por_user_id' => Auth::id(),
                'observaciones' => $observaciones,
                'tipo_calculo' => $tipoCalculo,
                'proyecto_id' => $proyectoId,
                'departamento_id' => $departamentoId,
            ]);

            // Calcular días hábiles del período
            $diasHabiles = $this->calcularDiasHabiles($periodoInicio, $periodoFin);

            // Obtener personal activo según el ámbito
            $query = Personal::where('estado', 'activo')
                ->with(['asignacionesActivas.configuracionPuesto', 'asignacionesActivas.turno']);

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

            // Si se especifica departamento, filtrar solo personal de ese departamento
            if ($departamentoId) {
                $query->where('departamento_id', $departamentoId);
            }

            $personal = $query->get();

            if ($personal->isEmpty()) {
                throw new \Exception('No se encontró personal activo para el ámbito especificado');
            }

            $totalDevengado = 0;
            $totalDescuentos = 0;
            $totalNeto = 0;

            // Generar detalle para cada empleado
            foreach ($personal as $empleado) {
                $detalle = $this->calcularDetalleEmpleado(
                    $empleado,
                    $planilla,
                    $periodoInicio,
                    $periodoFin,
                    $diasHabiles,
                    $strategy
                );

                if ($detalle) {
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
     * Calcula el detalle de planilla para un empleado.
     */
    private function calcularDetalleEmpleado(
        Personal $empleado,
        Planilla $planilla,
        string $periodoInicio,
        string $periodoFin,
        int $diasHabiles,
        PlanillaCalculoStrategy $strategy
    ): ?PlanillaDetalle {
        // Obtener asignación activa (la más reciente)
        $asignacionActiva = $empleado->asignacionesActivas
            ->sortByDesc('fecha_inicio')
            ->first();

        // Determinar el turno y proyecto
        $turno = $asignacionActiva?->turno;
        $proyectoId = $asignacionActiva?->proyecto_id;
        $horasPorTurno = TurnoHelper::horasTrabajadas($turno);

        // Calcular días y horas trabajadas
        $diasTrabajados = $this->contarDiasTrabajados($empleado->id, $periodoInicio, $periodoFin);
        $horasTrabajadas = $diasTrabajados * $horasPorTurno;

        // Calcular descuentos
        $descuentos = $this->calcularDescuentos($empleado->id, $periodoInicio, $periodoFin);

        // Solo crear detalle si trabajó días o tiene descuentos
        if ($diasTrabajados <= 0 && $descuentos['total'] <= 0) {
            return null;
        }

        // Preparar datos del empleado para la estrategia
        $datosEmpleado = [
            'salario_base' => $empleado->salario_base ?? 0,
            'turno' => $turno,
            'asignacion_activa' => $asignacionActiva ? [
                // NOTA: pago_hora_personal es en realidad un valor MENSUAL (nombre histórico incorrecto)
                'pago_mensual' => $asignacionActiva->configuracionPuesto?->pago_hora_personal ?? 0,
                'turno' => $turno,
            ] : null,
        ];

        // Calcular salario devengado usando la estrategia
        $salarioDevengado = $strategy->calcularSalarioDevengado(
            $datosEmpleado,
            $diasHabiles,
            $diasTrabajados,
            $horasTrabajadas
        );

        // Calcular pago por hora (para referencia)
        $pagoPorHora = ($diasHabiles > 0 && $horasPorTurno > 0)
            ? $this->obtenerSalarioMensual($datosEmpleado) / ($diasHabiles * $horasPorTurno)
            : 0;

        // Calcular salario neto
        $salarioNeto = max($salarioDevengado - $descuentos['total'], 0);

        // Crear detalle
        return PlanillaDetalle::create([
            'planilla_id' => $planilla->id,
            'personal_id' => $empleado->id,
            'proyecto_id' => $proyectoId,
            'dias_trabajados' => $diasTrabajados,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_por_turno' => $horasPorTurno,
            'pago_por_hora' => round($pagoPorHora, 2),
            'salario_devengado' => $salarioDevengado,
            'descuento_multas' => $descuentos['multa'],
            'descuento_uniformes' => $descuentos['uniforme'],
            'descuento_anticipos' => $descuentos['anticipo'],
            'descuento_prestamos' => $descuentos['abono_prestamo'],
            'descuento_antecedentes' => $descuentos['antecedentes'],
            'otros_descuentos' => $descuentos['otro_descuento'],
            'total_descuentos' => $descuentos['total'],
            'salario_neto' => $salarioNeto,
            'tipo_calculo' => $strategy->getNombre(),
        ]);
    }

    /**
     * Obtiene el salario mensual según la lógica del Caso 1.
     */
    private function obtenerSalarioMensual(array $empleado): float
    {
        if (isset($empleado['asignacion_activa']) && $empleado['asignacion_activa'] !== null) {
            $pagoMensual = $empleado['asignacion_activa']['pago_mensual'] ?? 0;
            if ($pagoMensual > 0) {
                return (float) $pagoMensual;
            }
        }
        return (float) ($empleado['salario_base'] ?? 0);
    }

    /**
     * Calcula los días hábiles entre dos fechas.
     * Por simplicidad, cuenta todos los días del período.
     * Puede ser extendido para excluir fines de semana/feriados.
     */
    private function calcularDiasHabiles(string $inicio, string $fin): int
    {
        $fechaInicio = Carbon::parse($inicio);
        $fechaFin = Carbon::parse($fin);

        // Contar solo días de lunes a viernes
        $diasHabiles = 0;
        $fecha = $fechaInicio->copy();

        while ($fecha <= $fechaFin) {
            if ($fecha->isWeekday()) {
                $diasHabiles++;
            }
            $fecha->addDay();
        }

        return $diasHabiles > 0 ? $diasHabiles : 1;
    }

    /**
     * Cuenta los días trabajados de un empleado en el período.
     */
    private function contarDiasTrabajados(int $personalId, string $inicio, string $fin): int
    {
        return DB::table('operaciones_asistencia as oa')
            ->leftJoin('operaciones_personal_asignado as opa', 'oa.personal_asignado_id', '=', 'opa.id')
            ->where(function ($query) use ($personalId) {
                $query->where('opa.personal_id', $personalId)
                      ->orWhere(function ($q) use ($personalId) {
                          $q->where('oa.personal_id', $personalId)
                            ->whereNull('oa.personal_asignado_id');
                      });
            })
            ->whereBetween('oa.fecha_asistencia', [$inicio, $fin])
            ->where('oa.es_descanso', false)
            ->where(function ($q) {
                $q->whereNull('oa.es_ausente')
                  ->orWhere('oa.es_ausente', false);
            })
            ->whereNotNull('oa.hora_entrada')
            ->distinct()
            ->count('oa.fecha_asistencia');
    }

    /**
     * Calcula los descuentos pendientes de un empleado en el período.
     * No marca las transacciones como aplicadas (eso ocurre al aprobar).
     */
    private function calcularDescuentos(int $personalId, string $inicio, string $fin): array
    {
        $descuentos = [
            'multa' => 0,
            'uniforme' => 0,
            'anticipo' => 0,
            'abono_prestamo' => 0,
            'antecedentes' => 0,
            'otro_descuento' => 0,
            'total' => 0,
        ];

        $transacciones = Transaccion::where('personal_id', $personalId)
            ->whereBetween('fecha_transaccion', [$inicio, $fin])
            ->where('estado_transaccion', 'pendiente')
            ->where('es_descuento', true)
            ->get();

        foreach ($transacciones as $transaccion) {
            $tipo = $transaccion->tipo_transaccion;
            if (isset($descuentos[$tipo])) {
                $descuentos[$tipo] += $transaccion->monto;
            }
            $descuentos['total'] += $transaccion->monto;
        }

        return $descuentos;
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

    /**
     * Valida que no exista una planilla activa (no cancelada) para el mismo período y ámbito.
     * Estados que bloquean: borrador, revision, aprobada, pagada
     * Estados que permiten crear: cancelada
     */
    private function validarPlanillaExistente(
        string $periodoInicio,
        string $periodoFin,
        ?int $proyectoId,
        ?int $departamentoId
    ): void {
        $planillaExistente = Planilla::porPeriodo($periodoInicio, $periodoFin)
            ->porAmbito($proyectoId, $departamentoId)
            ->activa() // Excluye canceladas
            ->first();

        if ($planillaExistente) {
            $estado = $planillaExistente->estado_label;
            $ambito = $this->describirAmbito($proyectoId, $departamentoId);

            throw new \Exception(
                "Ya existe una planilla {$ambito} para el período {$periodoInicio} - {$periodoFin} " .
                "en estado '{$estado}'. Debe cancelarla primero para crear una nueva."
            );
        }
    }

    /**
     * Valida que no existan días ya pagados dentro del rango de fechas.
     * Verifica traslape con planillas en estado 'pagada'.
     */
    private function validarDiasPagados(
        string $periodoInicio,
        string $periodoFin,
        ?int $proyectoId,
        ?int $departamentoId
    ): void {
        $planillasPagadas = Planilla::pagada()
            ->porAmbito($proyectoId, $departamentoId)
            ->conTraslapeFechas($periodoInicio, $periodoFin)
            ->get();

        if ($planillasPagadas->isNotEmpty()) {
            $periodosConflicto = $planillasPagadas->map(function ($p) {
                return "{$p->periodo_inicio->format('Y-m-d')} - {$p->periodo_fin->format('Y-m-d')}";
            })->implode(', ');

            throw new \Exception(
                "El período solicitado tiene traslape con planillas ya pagadas: {$periodosConflicto}. " .
                "No se pueden pagar los mismos días dos veces."
            );
        }
    }

    /**
     * Genera el nombre descriptivo de la planilla según su ámbito.
     */
    private function generarNombrePlanilla(
        string $periodoInicio,
        string $periodoFin,
        ?int $proyectoId,
        ?int $departamentoId
    ): string {
        $nombre = "Planilla {$periodoInicio} - {$periodoFin}";

        if ($proyectoId) {
            $proyecto = \App\Models\Proyecto::find($proyectoId);
            if ($proyecto) {
                $nombre .= " - {$proyecto->nombre}";
            }
        }

        if ($departamentoId) {
            $departamento = \App\Models\Catalogos\Departamento::find($departamentoId);
            if ($departamento) {
                $nombre .= " - {$departamento->nombre}";
            }
        }

        if (!$proyectoId && !$departamentoId) {
            $nombre .= " - General";
        }

        return $nombre;
    }

    /**
     * Describe el ámbito de la planilla para mensajes de error.
     */
    private function describirAmbito(?int $proyectoId, ?int $departamentoId): string
    {
        $partes = [];

        if ($proyectoId) {
            $proyecto = \App\Models\Proyecto::find($proyectoId);
            $partes[] = "del proyecto '{$proyecto?->nombre}'";
        }

        if ($departamentoId) {
            $departamento = \App\Models\Catalogos\Departamento::find($departamentoId);
            $partes[] = "del departamento '{$departamento?->nombre}'";
        }

        if (empty($partes)) {
            return 'general';
        }

        return implode(' y ', $partes);
    }

    /**
     * Recalcular una planilla existente (solo si está en borrador)
     *
     * @param int $planillaId ID de la planilla a recalcular
     * @return Planilla
     * @throws \Exception
     */
    public function recalcularPlanilla(int $planillaId): Planilla
    {
        $planilla = Planilla::findOrFail($planillaId);

        // Verificar que la planilla pueda ser editada
        if (!$planilla->puedeEditarse()) {
            throw new \Exception('Solo se pueden recalcular planillas en estado borrador o revisión');
        }

        DB::beginTransaction();

        try {
            // Eliminar detalles existentes
            $planilla->detalles()->delete();

            // Obtener la estrategia de cálculo
            $tipoCalculo = $planilla->tipo_calculo ?? config('planilla.tipo_calculo_default', 'caso_1');
            $strategy = PlanillaCalculoStrategyFactory::make($tipoCalculo);

            // Calcular días hábiles del período
            $diasHabiles = $this->calcularDiasHabiles($planilla->periodo_inicio, $planilla->periodo_fin);

            // Obtener personal activo según el ámbito de la planilla
            $query = Personal::where('estado', 'activo')
                ->with(['asignacionesActivas.configuracionPuesto', 'asignacionesActivas.turno']);

            // Aplicar filtros según el ámbito de la planilla
            if ($planilla->proyecto_id) {
                $query->whereHas('asignaciones', function ($q) use ($planilla) {
                    $q->where('proyecto_id', $planilla->proyecto_id)
                      ->where('estado_asignacion', 'activa')
                      ->where(function ($q2) use ($planilla) {
                          $q2->whereNull('fecha_fin')
                             ->orWhere('fecha_fin', '>=', $planilla->periodo_fin);
                      });
                });
            }

            if ($planilla->departamento_id) {
                $query->where('departamento_id', $planilla->departamento_id);
            }

            $personal = $query->get();

            if ($personal->isEmpty()) {
                throw new \Exception('No se encontró personal activo para el ámbito de la planilla');
            }

            $totalDevengado = 0;
            $totalDescuentos = 0;
            $totalNeto = 0;

            // Regenerar detalle para cada empleado
            foreach ($personal as $empleado) {
                $detalle = $this->calcularDetalleEmpleado(
                    $empleado,
                    $planilla,
                    $planilla->periodo_inicio,
                    $planilla->periodo_fin,
                    $diasHabiles,
                    $strategy
                );

                if ($detalle) {
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
}
