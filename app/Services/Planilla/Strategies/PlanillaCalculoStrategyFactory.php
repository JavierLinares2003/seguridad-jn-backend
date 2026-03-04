<?php

namespace App\Services\Planilla\Strategies;

use InvalidArgumentException;

class PlanillaCalculoStrategyFactory
{
    /**
     * Registro de estrategias disponibles.
     * Para agregar una nueva estrategia, solo se debe registrar aquí.
     *
     * @var array<string, class-string<PlanillaCalculoStrategy>>
     */
    private static array $strategies = [
        'caso_1' => Caso1Strategy::class,
        // Aquí se registran casos futuros:
        // 'caso_2' => Caso2Strategy::class,
    ];

    /**
     * Crea una instancia de la estrategia solicitada.
     *
     * @param string $tipo Identificador de la estrategia (ej: "caso_1")
     * @return PlanillaCalculoStrategy
     * @throws InvalidArgumentException Si la estrategia no existe
     */
    public static function make(string $tipo): PlanillaCalculoStrategy
    {
        if (!isset(self::$strategies[$tipo])) {
            $disponibles = implode(', ', array_keys(self::$strategies));
            throw new InvalidArgumentException(
                "Estrategia de cálculo '{$tipo}' no encontrada. Disponibles: {$disponibles}"
            );
        }

        $strategyClass = self::$strategies[$tipo];

        return new $strategyClass();
    }

    /**
     * Retorna la lista de estrategias disponibles.
     *
     * @return array<string> Lista de identificadores de estrategias
     */
    public static function disponibles(): array
    {
        return array_keys(self::$strategies);
    }

    /**
     * Verifica si una estrategia existe.
     *
     * @param string $tipo
     * @return bool
     */
    public static function existe(string $tipo): bool
    {
        return isset(self::$strategies[$tipo]);
    }
}
