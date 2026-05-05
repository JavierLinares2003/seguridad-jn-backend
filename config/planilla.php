<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tipo de Cálculo por Defecto
    |--------------------------------------------------------------------------
    |
    | Define la estrategia de cálculo que se usará por defecto al generar
    | planillas. Los valores disponibles son:
    |
    | - 'caso_1': Calcula usando salario mensual / (días hábiles × horas turno)
    |
    | Para agregar nuevas estrategias, ver:
    | App\Services\Planilla\Strategies\PlanillaCalculoStrategyFactory
    |
    */
    'tipo_calculo_default' => env('PLANILLA_TIPO_CALCULO', 'caso_2'),

    /*
    |--------------------------------------------------------------------------
    | Porcentaje de Descuento IGSS
    |--------------------------------------------------------------------------
    |
    | Porcentaje de la contribución del empleado al IGSS.
    | Se aplica sobre: (salario_base / 30) × días_calendario_del_período
    | Solo aplica a empleados con tiene_igss = true.
    |
    */
    'igss_porcentaje' => env('PLANILLA_IGSS_PORCENTAJE', 0.0483),

    /*
    |--------------------------------------------------------------------------
    | Año de Inicio de Tracking de Vacaciones
    |--------------------------------------------------------------------------
    |
    | Año a partir del cual el sistema empieza a acumular días de vacaciones
    | automáticamente. Los días previos se registran como saldo_inicial en
    | cada empleado.
    |
    */
    'vacaciones_anio_inicio' => env('VACACIONES_ANIO_INICIO', 2026),

    /*
    |--------------------------------------------------------------------------
    | Descuentos Fijos por Tipo de Inasistencia
    |--------------------------------------------------------------------------
    |
    | Cuotas fijas que se descuentan según la duración de la inasistencia.
    | Se aplican en la generación de planilla (Caso 2).
    |
    */
    'descuento_inasistencia_12h' => env('PLANILLA_DESCUENTO_INASISTENCIA_12H', 200),
    'descuento_inasistencia_24h' => env('PLANILLA_DESCUENTO_INASISTENCIA_24H', 400),
];
