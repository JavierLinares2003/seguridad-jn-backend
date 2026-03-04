<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PrestamoService
{
    /**
     * Genera las cuotas mensuales automáticamente para un préstamo.
     *
     * @param Prestamo $prestamo
     * @param int|null $userId Usuario que registra las cuotas
     * @return int Número de cuotas generadas
     */
    public function generarCuotasAutomaticas(Prestamo $prestamo, ?int $userId = null): int
    {
        if ($prestamo->cuotas_totales <= 0) {
            return 0;
        }

        $cuotasGeneradas = 0;
        $fechaPago = Carbon::parse($prestamo->fecha_primer_pago);

        for ($i = 0; $i < $prestamo->cuotas_totales; $i++) {
            Transaccion::create([
                'personal_id' => $prestamo->personal_id,
                'tipo_transaccion' => 'abono_prestamo',
                'monto' => $prestamo->monto_cuota,
                'descripcion' => "Cuota " . ($i + 1) . " de " . $prestamo->cuotas_totales . " - Préstamo #" . $prestamo->id,
                'fecha_transaccion' => $fechaPago->toDateString(),
                'es_descuento' => true,
                'estado_transaccion' => 'pendiente',
                'prestamo_id' => $prestamo->id,
                'registrado_por_user_id' => $userId,
            ]);

            $cuotasGeneradas++;
            // Siguiente mes
            $fechaPago->addMonth();
        }

        return $cuotasGeneradas;
    }

    /**
     * Recalcula las cuotas restantes después de un abono adicional.
     * Cancela las cuotas pendientes y genera nuevas con el saldo actualizado.
     *
     * @param Prestamo $prestamo
     * @param int|null $userId
     * @return array ['cuotas_canceladas' => int, 'cuotas_generadas' => int]
     */
    public function recalcularCuotasRestantes(Prestamo $prestamo, ?int $userId = null): array
    {
        DB::beginTransaction();

        try {
            // Obtener préstamo actualizado
            $prestamo->refresh();

            // Cancelar todas las cuotas pendientes futuras
            $cuotasCanceladas = Transaccion::where('prestamo_id', $prestamo->id)
                ->where('tipo_transaccion', 'abono_prestamo')
                ->where('estado_transaccion', 'pendiente')
                ->where('fecha_transaccion', '>', now()->toDateString())
                ->update([
                    'estado_transaccion' => 'cancelado',
                    'descripcion' => DB::raw("descripcion || ' [CANCELADA - Recálculo por abono extra]'")
                ]);

            // Calcular cuotas restantes
            $cuotasRestantes = $prestamo->cuotas_totales - $prestamo->cuotas_pagadas;

            if ($cuotasRestantes <= 0 || $prestamo->saldo_pendiente <= 0) {
                DB::commit();
                return [
                    'cuotas_canceladas' => $cuotasCanceladas,
                    'cuotas_generadas' => 0,
                ];
            }

            // Calcular nuevo monto por cuota
            $nuevoMontoCuota = round($prestamo->saldo_pendiente / $cuotasRestantes, 2);

            // Actualizar el monto_cuota en el préstamo
            $prestamo->update(['monto_cuota' => $nuevoMontoCuota]);

            // Generar nuevas cuotas con el monto recalculado
            $cuotasGeneradas = 0;
            $fechaPago = Carbon::now()->addMonth()->startOfMonth();

            // Buscar la fecha de la próxima cuota original si existe
            $proximaCuotaOriginal = Transaccion::where('prestamo_id', $prestamo->id)
                ->where('tipo_transaccion', 'abono_prestamo')
                ->where('estado_transaccion', 'pendiente')
                ->orderBy('fecha_transaccion', 'asc')
                ->first();

            if ($proximaCuotaOriginal) {
                $fechaPago = Carbon::parse($proximaCuotaOriginal->fecha_transaccion);
            }

            for ($i = 0; $i < $cuotasRestantes; $i++) {
                $esUltimaCuota = ($i == $cuotasRestantes - 1);

                // Ajustar última cuota para cubrir cualquier diferencia de redondeo
                $montoCuota = $esUltimaCuota
                    ? $prestamo->saldo_pendiente - ($nuevoMontoCuota * ($cuotasRestantes - 1))
                    : $nuevoMontoCuota;

                Transaccion::create([
                    'personal_id' => $prestamo->personal_id,
                    'tipo_transaccion' => 'abono_prestamo',
                    'monto' => $montoCuota,
                    'descripcion' => "Cuota " . ($prestamo->cuotas_pagadas + $i + 1) . " de " . $prestamo->cuotas_totales .
                                   " (Recalculada) - Préstamo #" . $prestamo->id,
                    'fecha_transaccion' => $fechaPago->toDateString(),
                    'es_descuento' => true,
                    'estado_transaccion' => 'pendiente',
                    'prestamo_id' => $prestamo->id,
                    'registrado_por_user_id' => $userId,
                ]);

                $cuotasGeneradas++;
                $fechaPago->addMonth();
            }

            DB::commit();

            return [
                'cuotas_canceladas' => $cuotasCanceladas,
                'cuotas_generadas' => $cuotasGeneradas,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Procesa un abono a un préstamo.
     * Determina si es un abono normal, extra, o liquidación total.
     *
     * @param Prestamo $prestamo
     * @param float $monto
     * @param string $descripcion
     * @param int|null $userId
     * @return array Resultado del procesamiento
     */
    public function procesarAbono(Prestamo $prestamo, float $monto, string $descripcion, ?int $userId = null): array
    {
        DB::beginTransaction();

        try {
            $prestamo->refresh();

            // Verificar si es liquidación total
            $esLiquidacionTotal = abs($monto - $prestamo->saldo_pendiente) < 0.01;

            // Verificar si es abono extra (mayor que la cuota mensual)
            $esAbonoExtra = $monto > $prestamo->monto_cuota && !$esLiquidacionTotal;

            // Crear la transacción de abono
            $transaccion = Transaccion::create([
                'personal_id' => $prestamo->personal_id,
                'tipo_transaccion' => 'abono_prestamo',
                'monto' => $monto,
                'descripcion' => $descripcion . ($esLiquidacionTotal ? ' [LIQUIDACIÓN TOTAL]' : ($esAbonoExtra ? ' [ABONO EXTRA]' : '')),
                'fecha_transaccion' => now()->toDateString(),
                'es_descuento' => false, // Los abonos manuales no son descuentos automáticos
                'estado_transaccion' => 'aplicado', // Se aplica inmediatamente
                'prestamo_id' => $prestamo->id,
                'registrado_por_user_id' => $userId,
            ]);

            $resultado = [
                'transaccion' => $transaccion,
                'tipo_abono' => $esLiquidacionTotal ? 'liquidacion_total' : ($esAbonoExtra ? 'abono_extra' : 'abono_normal'),
                'cuotas_recalculadas' => false,
            ];

            // Si es abono extra o liquidación, recalcular cuotas
            if ($esAbonoExtra || $esLiquidacionTotal) {
                $resultado['recalculo'] = $this->recalcularCuotasRestantes($prestamo, $userId);
                $resultado['cuotas_recalculadas'] = true;
            }

            DB::commit();

            return $resultado;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancela todas las cuotas pendientes de un préstamo.
     * Útil cuando se cancela un préstamo o se liquida completamente.
     *
     * @param Prestamo $prestamo
     * @return int Número de cuotas canceladas
     */
    public function cancelarCuotasPendientes(Prestamo $prestamo): int
    {
        return Transaccion::where('prestamo_id', $prestamo->id)
            ->where('tipo_transaccion', 'abono_prestamo')
            ->where('estado_transaccion', 'pendiente')
            ->update([
                'estado_transaccion' => 'cancelado',
                'descripcion' => DB::raw("descripcion || ' [CANCELADA]'")
            ]);
    }

    /**
     * Obtiene un resumen del estado de un préstamo.
     *
     * @param Prestamo $prestamo
     * @return array
     */
    public function obtenerResumenPrestamo(Prestamo $prestamo): array
    {
        $prestamo->refresh();

        $cuotasPendientes = Transaccion::where('prestamo_id', $prestamo->id)
            ->where('tipo_transaccion', 'abono_prestamo')
            ->where('estado_transaccion', 'pendiente')
            ->count();

        $cuotasAplicadas = Transaccion::where('prestamo_id', $prestamo->id)
            ->where('tipo_transaccion', 'abono_prestamo')
            ->where('estado_transaccion', 'aplicado')
            ->count();

        $totalAbonado = $prestamo->monto_total - $prestamo->saldo_pendiente;

        return [
            'monto_total' => $prestamo->monto_total,
            'saldo_pendiente' => $prestamo->saldo_pendiente,
            'total_abonado' => $totalAbonado,
            'porcentaje_pagado' => $prestamo->porcentaje_pagado,
            'cuotas_totales' => $prestamo->cuotas_totales,
            'cuotas_pagadas' => $prestamo->cuotas_pagadas,
            'cuotas_restantes' => $prestamo->cuotas_totales - $prestamo->cuotas_pagadas,
            'cuotas_pendientes_programadas' => $cuotasPendientes,
            'cuotas_aplicadas' => $cuotasAplicadas,
            'monto_cuota_actual' => $prestamo->monto_cuota,
            'estado' => $prestamo->estado_prestamo,
        ];
    }
}
