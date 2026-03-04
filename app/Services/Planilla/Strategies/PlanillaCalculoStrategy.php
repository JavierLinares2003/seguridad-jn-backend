<?php

namespace App\Services\Planilla\Strategies;

interface PlanillaCalculoStrategy
{
    /**
     * Calcula el salario devengado para un empleado en el período.
     *
     * @param array $empleado Datos del empleado:
     *   - salario_base: float - Salario base del empleado (usado si no tiene asignación)
     *   - asignacion_activa: array|null - Datos de la asignación activa:
     *       - pago_mensual: float - Pago mensual configurado en el proyecto
     *         (NOTA: En BD es "pago_hora_personal" pero es valor mensual, nombre histórico incorrecto)
     *       - turno: Turno|null - Modelo del turno asignado
     *   - turno: Turno|null - Turno del empleado (puede venir de asignación o configuración general)
     * @param int $diasHabiles Días hábiles del período (para calcular salario por hora)
     * @param int $diasTrabajados Días reales con asistencia registrada
     * @param float $horasTrabajadas Horas reales trabajadas (dias_trabajados × horas_por_turno)
     *
     * @return float Salario devengado calculado
     */
    public function calcularSalarioDevengado(
        array $empleado,
        int $diasHabiles,
        int $diasTrabajados,
        float $horasTrabajadas
    ): float;

    /**
     * Retorna el nombre/identificador de la estrategia.
     *
     * @return string Ej: "caso_1", "caso_2"
     */
    public function getNombre(): string;
}
