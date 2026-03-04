<?php

namespace App\Services\Planilla\Strategies;

use App\Services\Planilla\TurnoHelper;

class Caso1Strategy implements PlanillaCalculoStrategy
{
    /**
     * Calcula el salario devengado según el Caso 1:
     *
     * Regla de salario base:
     * - Empleado SIN asignación de proyecto → usar salario_base del empleado
     * - Empleado CON asignación de proyecto → usar pago_mensual del puesto en el proyecto
     *
     * Fórmula:
     * 1. salario_mensual = (según regla arriba)
     * 2. horas_por_turno = TurnoHelper::horasTrabajadas($turno)
     * 3. salario_por_hora = salario_mensual / (dias_habiles * horas_por_turno)
     * 4. salario_devengado = salario_por_hora * horas_trabajadas_reales
     *
     * @param array $empleado
     * @param int $diasHabiles
     * @param int $diasTrabajados
     * @param float $horasTrabajadas
     * @return float
     */
    public function calcularSalarioDevengado(
        array $empleado,
        int $diasHabiles,
        int $diasTrabajados,
        float $horasTrabajadas
    ): float {
        // 1. Determinar el salario mensual según la regla
        $salarioMensual = $this->obtenerSalarioMensual($empleado);

        if ($salarioMensual <= 0 || $diasHabiles <= 0) {
            return 0.0;
        }

        // 2. Obtener horas por turno
        $turno = $empleado['turno'] ?? null;
        $horasPorTurno = TurnoHelper::horasTrabajadas($turno);

        if ($horasPorTurno <= 0) {
            return 0.0;
        }

        // 3. Calcular salario por hora
        // salario_por_hora = salario_mensual / (dias_habiles * horas_por_turno)
        $horasMensualesEsperadas = $diasHabiles * $horasPorTurno;
        $salarioPorHora = $salarioMensual / $horasMensualesEsperadas;

        // 4. Calcular salario devengado
        // salario_devengado = salario_por_hora * horas_trabajadas_reales
        $salarioDevengado = $salarioPorHora * $horasTrabajadas;

        return round($salarioDevengado, 2);
    }

    /**
     * Obtiene el salario mensual según la regla del Caso 1.
     *
     * @param array $empleado
     * @return float
     */
    private function obtenerSalarioMensual(array $empleado): float
    {
        // Si tiene asignación activa, usar el pago configurado del proyecto
        if (isset($empleado['asignacion_activa']) && $empleado['asignacion_activa'] !== null) {
            $asignacion = $empleado['asignacion_activa'];

            // NOTA: El campo en BD se llama "pago_hora_personal" pero es un valor MENSUAL.
            // El nombre es históricamente incorrecto, no se renombró por compatibilidad.
            $pagoMensual = $asignacion['pago_mensual'] ?? 0;

            if ($pagoMensual > 0) {
                return (float) $pagoMensual;
            }
        }

        // Sin asignación o sin pago configurado → usar salario_base del empleado
        return (float) ($empleado['salario_base'] ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function getNombre(): string
    {
        return 'caso_1';
    }
}
