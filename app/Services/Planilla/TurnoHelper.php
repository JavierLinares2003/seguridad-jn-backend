<?php

namespace App\Services\Planilla;

use App\Models\Catalogos\Turno;

class TurnoHelper
{
    /**
     * Obtiene las horas de trabajo de un turno.
     *
     * Prioridad:
     * 1. Columna horas_trabajo del modelo (si existe y tiene valor)
     * 2. Parseo del nombre del turno como fallback
     *
     * @param Turno|string|null $turno Modelo Turno o nombre del turno (ej: "12x24")
     * @return float Horas de trabajo (ej: 12)
     */
    public static function horasTrabajadas(Turno|string|null $turno): float
    {
        if ($turno === null) {
            return 8.0; // Default: turno estándar de 8 horas
        }

        // Si es un modelo Turno, intentar usar la columna horas_trabajo
        if ($turno instanceof Turno) {
            if ($turno->horas_trabajo !== null && $turno->horas_trabajo > 0) {
                return (float) $turno->horas_trabajo;
            }
            // Fallback: parsear el nombre
            return self::parsearHorasTrabajo($turno->nombre);
        }

        // Si es string, parsear directamente
        return self::parsearHorasTrabajo($turno);
    }

    /**
     * Obtiene las horas de descanso de un turno.
     *
     * Siempre se parsea del nombre del turno ya que este campo
     * no existe en la base de datos.
     *
     * @param Turno|string|null $turno Modelo Turno o nombre del turno (ej: "12x24")
     * @return float Horas de descanso (ej: 24)
     */
    public static function horasDescanso(Turno|string|null $turno): float
    {
        if ($turno === null) {
            return 8.0; // Default: 8 horas de descanso
        }

        $nombre = $turno instanceof Turno ? $turno->nombre : $turno;

        return self::parsearHorasDescanso($nombre);
    }

    /**
     * Obtiene ambos valores (trabajo y descanso) del turno.
     *
     * @param Turno|string|null $turno
     * @return array{horas_trabajo: float, horas_descanso: float}
     */
    public static function parsearTurno(Turno|string|null $turno): array
    {
        return [
            'horas_trabajo' => self::horasTrabajadas($turno),
            'horas_descanso' => self::horasDescanso($turno),
        ];
    }

    /**
     * Parsea el nombre del turno para obtener las horas de trabajo.
     * Formato esperado: "{trabajo}x{descanso}" (ej: "12x24", "24x48")
     *
     * @param string|null $nombreTurno
     * @return float
     */
    private static function parsearHorasTrabajo(?string $nombreTurno): float
    {
        if (empty($nombreTurno)) {
            return 8.0;
        }

        // Formato: "12x24" → trabajo = 12
        if (preg_match('/^(\d+)x\d+$/i', $nombreTurno, $matches)) {
            return (float) $matches[1];
        }

        return 8.0; // Default si el formato no coincide
    }

    /**
     * Parsea el nombre del turno para obtener las horas de descanso.
     * Formato esperado: "{trabajo}x{descanso}" (ej: "12x24", "24x48")
     *
     * @param string|null $nombreTurno
     * @return float
     */
    private static function parsearHorasDescanso(?string $nombreTurno): float
    {
        if (empty($nombreTurno)) {
            return 8.0;
        }

        // Formato: "12x24" → descanso = 24
        if (preg_match('/^\d+x(\d+)$/i', $nombreTurno, $matches)) {
            return (float) $matches[1];
        }

        return 8.0; // Default si el formato no coincide
    }
}
