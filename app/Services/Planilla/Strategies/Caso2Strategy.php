<?php

namespace App\Services\Planilla\Strategies;

class Caso2Strategy implements PlanillaCalculoStrategy
{
    /**
     * Calcula el salario devengado según el Caso 2 (pago por día):
     *
     * Regla de salario:
     * - Siempre se usa el salario_base del empleado (configurado por RRHH).
     * - El valor del proyecto NO se utiliza.
     *
     * Días pagados:
     * - Días trabajados + días de descanso (ambos se pagan igual).
     * - Días ausentes: NO se pagan y generan una penalidad del 50% de la tarifa diaria.
     *   La penalidad se retorna en $empleado['descuento_ausencias'] para que
     *   PlanillaService la incluya en los descuentos del detalle.
     *
     * Fórmula:
     * 1. tarifa_diaria = salario_base / dias_habiles
     * 2. dias_pagados  = dias_trabajados + dias_descanso
     * 3. salario_devengado = tarifa_diaria × dias_pagados
     *
     * Nota: la penalidad por ausencias (tarifa_diaria × 0.5 × dias_ausentes)
     * se calcula en PlanillaService y se guarda en descuento_ausencias.
     *
     * @param array $empleado  Debe incluir 'salario_base' y opcionalmente 'dias_descanso'
     * @param int   $diasHabiles
     * @param int   $diasTrabajados  Solo días presentes (no descanso, no ausente)
     * @param float $horasTrabajadas  Ignorado en esta estrategia
     * @return float
     */
    public function calcularSalarioDevengado(
        array $empleado,
        int $diasHabiles,
        int $diasTrabajados,
        float $horasTrabajadas
    ): float {
        $salarioBase = (float) ($empleado['salario_base'] ?? 0);

        if ($salarioBase <= 0) {
            return 0.0;
        }

        // La tarifa diaria se calcula siempre sobre 30 días (mes estándar),
        // sin importar cuántos días tenga el período de la planilla.
        $tarifa = $salarioBase / 30;

        $diasDescanso = (int) ($empleado['dias_descanso'] ?? 0);
        $diasPagados  = $diasTrabajados + $diasDescanso;

        return round($tarifa * $diasPagados, 2);
    }

    /**
     * @inheritDoc
     */
    public function getNombre(): string
    {
        return 'caso_2';
    }
}
