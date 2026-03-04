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
    'tipo_calculo_default' => env('PLANILLA_TIPO_CALCULO', 'caso_1'),
];
