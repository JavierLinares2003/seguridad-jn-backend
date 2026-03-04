<?php

namespace App\Services;

use App\Models\Catalogos\Turno;
use App\Models\ProyectoConfiguracionPersonal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TurnoCalculadorService
{
    /**
     * Determina si un agente debe trabajar en una fecha específica
     * basándose en su turno y fecha de inicio de asignación.
     *
     * @param int $turnoId ID del turno
     * @param Carbon $fechaInicio Fecha de inicio de la asignación (referencia del ciclo)
     * @param Carbon $fechaConsulta Fecha a verificar
     * @return bool True si debe trabajar, False si es día de descanso
     */
    public function debeTrabajar(int $turnoId, Carbon $fechaInicio, Carbon $fechaConsulta): bool
    {
        $turno = Turno::find($turnoId);

        if (!$turno) {
            return true; // Por defecto, si no hay turno, asumimos que trabaja
        }

        $diasTrabajo = $turno->dias_trabajo ?? 1;
        $diasDescanso = $turno->dias_descanso ?? 0;

        // Si no hay días de descanso, siempre trabaja
        if ($diasDescanso === 0) {
            return true;
        }

        $cicloTotal = $diasTrabajo + $diasDescanso;

        // Calcular días transcurridos desde el inicio de la asignación
        $diasTranscurridos = $fechaInicio->diffInDays($fechaConsulta);

        // Calcular posición dentro del ciclo (0-indexed)
        $posicionEnCiclo = $diasTranscurridos % $cicloTotal;

        // Si está dentro de los días de trabajo, debe trabajar
        return $posicionEnCiclo < $diasTrabajo;
    }

    /**
     * Obtiene el tipo de día (trabajo/descanso) para una fecha específica
     *
     * @param int $turnoId
     * @param Carbon $fechaInicio
     * @param Carbon $fechaConsulta
     * @return array ['es_trabajo' => bool, 'dia_ciclo' => int, 'tipo' => string]
     */
    public function obtenerTipoDia(int $turnoId, Carbon $fechaInicio, Carbon $fechaConsulta): array
    {
        $turno = Turno::find($turnoId);

        if (!$turno) {
            return [
                'es_trabajo' => true,
                'dia_ciclo' => 1,
                'tipo' => 'trabajo',
                'turno_nombre' => 'Sin turno',
            ];
        }

        $diasTrabajo = $turno->dias_trabajo ?? 1;
        $diasDescanso = $turno->dias_descanso ?? 0;
        $cicloTotal = $diasTrabajo + $diasDescanso;

        // Si no hay días de descanso, siempre trabaja
        if ($diasDescanso === 0) {
            return [
                'es_trabajo' => true,
                'dia_ciclo' => 1,
                'tipo' => 'trabajo',
                'turno_nombre' => $turno->nombre,
            ];
        }

        $diasTranscurridos = $fechaInicio->diffInDays($fechaConsulta);
        $posicionEnCiclo = $diasTranscurridos % $cicloTotal;
        $esTrabajo = $posicionEnCiclo < $diasTrabajo;

        return [
            'es_trabajo' => $esTrabajo,
            'dia_ciclo' => $posicionEnCiclo + 1, // 1-indexed
            'tipo' => $esTrabajo ? 'trabajo' : 'descanso',
            'turno_nombre' => $turno->nombre,
            'dias_trabajo' => $diasTrabajo,
            'dias_descanso' => $diasDescanso,
        ];
    }

    /**
     * Genera el calendario de trabajo para un rango de fechas
     *
     * @param int $turnoId
     * @param Carbon $fechaInicio Fecha de inicio de la asignación
     * @param Carbon $rangoInicio Inicio del rango a generar
     * @param Carbon $rangoFin Fin del rango a generar
     * @return Collection
     */
    public function generarCalendario(int $turnoId, Carbon $fechaInicio, Carbon $rangoInicio, Carbon $rangoFin): Collection
    {
        $calendario = collect();
        $fechaActual = $rangoInicio->copy();

        while ($fechaActual->lte($rangoFin)) {
            $tipoDia = $this->obtenerTipoDia($turnoId, $fechaInicio, $fechaActual);
            $calendario->push([
                'fecha' => $fechaActual->format('Y-m-d'),
                'dia_semana' => $fechaActual->locale('es')->dayName,
                ...$tipoDia,
            ]);
            $fechaActual->addDay();
        }

        return $calendario;
    }

    /**
     * Calcula cuántos días de trabajo hay en un rango de fechas para un turno
     *
     * @param int $turnoId
     * @param Carbon $fechaInicio Fecha de inicio de la asignación
     * @param Carbon $rangoInicio
     * @param Carbon $rangoFin
     * @return int
     */
    public function contarDiasTrabajo(int $turnoId, Carbon $fechaInicio, Carbon $rangoInicio, Carbon $rangoFin): int
    {
        $diasTrabajo = 0;
        $fechaActual = $rangoInicio->copy();

        while ($fechaActual->lte($rangoFin)) {
            if ($this->debeTrabajar($turnoId, $fechaInicio, $fechaActual)) {
                $diasTrabajo++;
            }
            $fechaActual->addDay();
        }

        return $diasTrabajo;
    }

    /**
     * Calcula cuántos días de descanso hay en un rango de fechas para un turno
     *
     * @param int $turnoId
     * @param Carbon $fechaInicio
     * @param Carbon $rangoInicio
     * @param Carbon $rangoFin
     * @return int
     */
    public function contarDiasDescanso(int $turnoId, Carbon $fechaInicio, Carbon $rangoInicio, Carbon $rangoFin): int
    {
        $diasDescanso = 0;
        $fechaActual = $rangoInicio->copy();

        while ($fechaActual->lte($rangoFin)) {
            if (!$this->debeTrabajar($turnoId, $fechaInicio, $fechaActual)) {
                $diasDescanso++;
            }
            $fechaActual->addDay();
        }

        return $diasDescanso;
    }

    /**
     * Obtiene información del turno de un personal en un proyecto específico
     *
     * @param int $personalId
     * @param int $proyectoId
     * @return Turno|null
     */
    public function obtenerTurnoPersonal(int $personalId, int $proyectoId): ?Turno
    {
        // Buscar la configuración del personal en el proyecto
        $configuracion = ProyectoConfiguracionPersonal::where('proyecto_id', $proyectoId)
            ->whereHas('proyecto.asignaciones', function ($q) use ($personalId) {
                $q->where('personal_id', $personalId)
                  ->where('estado_asignacion', 'activa');
            })
            ->first();

        if ($configuracion && $configuracion->turno_id) {
            return Turno::find($configuracion->turno_id);
        }

        return null;
    }
}
