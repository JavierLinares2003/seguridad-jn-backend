<?php

namespace App\Services\Operacion;

use App\Models\Personal;
use App\Models\OperacionPersonalAsignado;
use App\Models\ProyectoConfiguracionPersonal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class PersonalDisponibilidadService
{
    /**
     * Obtiene el personal disponible en un rango de fechas.
     * Disponible = activo + sin asignaciones en conflicto
     */
    public function getPersonalDisponible(
        Carbon $fechaInicio,
        ?Carbon $fechaFin = null,
        ?int $configuracionPuestoId = null
    ): Collection {
        $query = Personal::query()
            ->activos()
            ->with(['sexo', 'departamento']);

        // Excluir personal con asignaciones activas en el rango de fechas
        $personalConAsignacion = $this->getPersonalConAsignacionEnRango($fechaInicio, $fechaFin);

        if ($personalConAsignacion->isNotEmpty()) {
            $query->whereNotIn('id', $personalConAsignacion->toArray());
        }

        // Si hay configuración de puesto, filtrar por requisitos
        if ($configuracionPuestoId) {
            $config = ProyectoConfiguracionPersonal::find($configuracionPuestoId);
            if ($config) {
                $query = $this->aplicarFiltrosRequisitos($query, $config);
            }
        }

        return $query->get()->map(function ($personal) use ($fechaInicio, $fechaFin, $configuracionPuestoId) {
            $personal->disponibilidad = [
                'fecha_consulta_inicio' => $fechaInicio->toDateString(),
                'fecha_consulta_fin' => $fechaFin?->toDateString(),
                'cumple_requisitos' => $configuracionPuestoId
                    ? $this->cumpleRequisitos($personal, $configuracionPuestoId)
                    : null,
            ];
            return $personal;
        });
    }

    /**
     * Verifica si un personal específico está disponible en un rango de fechas.
     */
    public function estaDisponible(
        int $personalId,
        Carbon $fechaInicio,
        ?Carbon $fechaFin = null,
        ?int $excluirAsignacionId = null
    ): bool {
        $query = OperacionPersonalAsignado::query()
            ->activas()
            ->byPersonal($personalId);

        if ($excluirAsignacionId) {
            $query->where('id', '!=', $excluirAsignacionId);
        }

        // Verificar solapamiento de fechas
        $tieneConflicto = $query->where(function ($q) use ($fechaInicio, $fechaFin) {
            $q->where(function ($subQ) use ($fechaInicio, $fechaFin) {
                // Asignación sin fecha fin (indefinida)
                $subQ->whereNull('fecha_fin')
                     ->where(function ($inner) use ($fechaInicio, $fechaFin) {
                         if ($fechaFin) {
                             $inner->where('fecha_inicio', '<=', $fechaFin);
                         }
                     });
            })->orWhere(function ($subQ) use ($fechaInicio, $fechaFin) {
                // Asignación con fecha fin
                $subQ->whereNotNull('fecha_fin')
                     ->where('fecha_fin', '>=', $fechaInicio);
                if ($fechaFin) {
                    $subQ->where('fecha_inicio', '<=', $fechaFin);
                }
            });
        })->exists();

        return !$tieneConflicto;
    }

    /**
     * Obtiene las asignaciones activas de un personal en un rango de fechas.
     */
    public function getAsignacionesEnRango(
        int $personalId,
        Carbon $fechaInicio,
        ?Carbon $fechaFin = null
    ): Collection {
        return OperacionPersonalAsignado::query()
            ->activas()
            ->byPersonal($personalId)
            ->enRangoFechas($fechaInicio, $fechaFin)
            ->with(['proyecto', 'configuracionPuesto', 'turno'])
            ->get();
    }

    /**
     * Verifica si el personal cumple con los requisitos de un puesto.
     */
    public function cumpleRequisitos(Personal $personal, int $configuracionPuestoId): array
    {
        $config = ProyectoConfiguracionPersonal::with(['sexo', 'turno'])->find($configuracionPuestoId);

        if (!$config) {
            return [
                'cumple' => false,
                'errores' => ['La configuración del puesto no existe.'],
                'detalles' => [],
            ];
        }

        $errores = [];
        $detalles = [];

        // Validar edad
        $edad = $personal->edad;
        $cumpleEdad = $edad >= $config->edad_minima && $edad <= $config->edad_maxima;
        
        $detalles['edad'] = [
            'valor_personal' => $edad,
            'minimo_requerido' => $config->edad_minima,
            'maximo_requerido' => $config->edad_maxima,
            'cumple' => $cumpleEdad,
        ];

        if ($edad < $config->edad_minima) {
            $errores[] = "Edad ({$edad} años) menor al mínimo requerido ({$config->edad_minima} años).";
        }
        if ($edad > $config->edad_maxima) {
            $errores[] = "Edad ({$edad} años) mayor al máximo permitido ({$config->edad_maxima} años).";
        }

        // Validar sexo (si está especificado)
        $cumpleSexo = !$config->sexo_id || $personal->sexo_id === $config->sexo_id;
        
        if ($config->sexo_id) {
            $detalles['sexo'] = [
                'valor_personal' => $personal->sexo->nombre ?? 'No especificado',
                'requerido' => $config->sexo->nombre ?? 'No especificado',
                'cumple' => $cumpleSexo,
            ];

            if (!$cumpleSexo) {
                $errores[] = 'El sexo no coincide con el requerido para el puesto.';
            }
        }

        // Validar altura (si está especificada)
        $cumpleAltura = !$config->altura_minima || ($personal->altura && $personal->altura >= $config->altura_minima);
        
        if ($config->altura_minima) {
            $detalles['altura'] = [
                'valor_personal' => $personal->altura ? round($personal->altura, 2) : null,
                'minimo_requerido' => round($config->altura_minima, 2),
                'cumple' => $cumpleAltura,
            ];

            if ($personal->altura && $personal->altura < $config->altura_minima) {
                $errores[] = "Altura ({$personal->altura} m) menor a la requerida ({$config->altura_minima} m).";
            } elseif (!$personal->altura) {
                $errores[] = "Altura no registrada (se requiere mínimo {$config->altura_minima} m).";
            }
        }

        // Información adicional del puesto
        $detalles['puesto'] = [
            'nombre' => $config->nombre_puesto,
            'turno' => $config->turno->nombre ?? 'No especificado',
            'salario_base' => $config->salario_base,
        ];

        // Información del personal
        $detalles['personal'] = [
            'nombre_completo' => $personal->nombres . ' ' . $personal->apellidos,
            'dpi' => $personal->dpi,
            'edad' => $edad,
            'sexo' => $personal->sexo->nombre ?? 'No especificado',
            'altura' => $personal->altura ? round($personal->altura, 2) : null,
            'departamento' => $personal->departamento->nombre ?? 'No especificado',
        ];

        return [
            'cumple' => empty($errores),
            'errores' => $errores,
            'detalles' => $detalles,
        ];
    }

    /**
     * Obtiene el calendario de disponibilidad de un personal.
     */
    public function getCalendarioDisponibilidad(
        int $personalId,
        Carbon $fechaInicio,
        Carbon $fechaFin
    ): array {
        $asignaciones = $this->getAsignacionesEnRango($personalId, $fechaInicio, $fechaFin);

        $calendario = [];
        $fechaActual = $fechaInicio->copy();

        while ($fechaActual <= $fechaFin) {
            $fechaStr = $fechaActual->toDateString();
            $asignacionDia = null;

            foreach ($asignaciones as $asignacion) {
                if (
                    $asignacion->fecha_inicio <= $fechaActual &&
                    ($asignacion->fecha_fin === null || $asignacion->fecha_fin >= $fechaActual)
                ) {
                    $asignacionDia = $asignacion;
                    break;
                }
            }

            $calendario[$fechaStr] = [
                'disponible' => $asignacionDia === null,
                'asignacion' => $asignacionDia ? [
                    'id' => $asignacionDia->id,
                    'proyecto' => $asignacionDia->proyecto->nombre_proyecto ?? null,
                    'turno' => $asignacionDia->turno->nombre ?? null,
                ] : null,
            ];

            $fechaActual->addDay();
        }

        return $calendario;
    }

    /**
     * Obtiene estadísticas de asignación de un proyecto.
     */
    public function getEstadisticasProyecto(int $proyectoId): array
    {
        $configuraciones = ProyectoConfiguracionPersonal::where('proyecto_id', $proyectoId)
            ->where('estado', 'activo')
            ->get();

        $estadisticas = [];

        foreach ($configuraciones as $config) {
            $asignadosActivos = OperacionPersonalAsignado::query()
                ->activas()
                ->vigentes()
                ->where('configuracion_puesto_id', $config->id)
                ->count();

            $estadisticas[] = [
                'configuracion_id' => $config->id,
                'nombre_puesto' => $config->nombre_puesto,
                'cantidad_requerida' => $config->cantidad_requerida,
                'cantidad_asignada' => $asignadosActivos,
                'faltantes' => max(0, $config->cantidad_requerida - $asignadosActivos),
                'porcentaje_cubierto' => $config->cantidad_requerida > 0
                    ? round(($asignadosActivos / $config->cantidad_requerida) * 100, 2)
                    : 0,
            ];
        }

        return [
            'puestos' => $estadisticas,
            'resumen' => [
                'total_requerido' => collect($estadisticas)->sum('cantidad_requerida'),
                'total_asignado' => collect($estadisticas)->sum('cantidad_asignada'),
                'total_faltantes' => collect($estadisticas)->sum('faltantes'),
            ],
        ];
    }

    /**
     * Obtiene IDs de personal con asignaciones activas en un rango.
     */
    private function getPersonalConAsignacionEnRango(Carbon $fechaInicio, ?Carbon $fechaFin): Collection
    {
        return OperacionPersonalAsignado::query()
            ->activas()
            ->enRangoFechas($fechaInicio, $fechaFin)
            ->pluck('personal_id')
            ->unique();
    }

    /**
     * Aplica filtros de requisitos del puesto a la consulta de personal.
     */
    private function aplicarFiltrosRequisitos(Builder $query, ProyectoConfiguracionPersonal $config): Builder
    {
        // Filtrar por edad usando la fecha de nacimiento
        $fechaMinNacimiento = Carbon::today()->subYears($config->edad_maxima)->startOfDay();
        $fechaMaxNacimiento = Carbon::today()->subYears($config->edad_minima)->endOfDay();

        $query->whereBetween('fecha_nacimiento', [$fechaMinNacimiento, $fechaMaxNacimiento]);

        // Filtrar por sexo si está especificado
        if ($config->sexo_id) {
            $query->where('sexo_id', $config->sexo_id);
        }

        // Filtrar por altura mínima si está especificada
        if ($config->altura_minima) {
            $query->where('altura', '>=', $config->altura_minima);
        }

        return $query;
    }
}
