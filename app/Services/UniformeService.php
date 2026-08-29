<?php

namespace App\Services;

use App\Models\Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UniformeService
{
    /**
     * Genera fechas quincenales a partir de una fecha de inicio.
     * Alterna entre día 15 y fin de mes (último día del mes).
     *
     * @return array<int, string> Fechas Y-m-d
     */
    public function generarFechasQuincenales(string $fechaInicio, int $cuotas): array
    {
        $fechas = [];
        $fecha = Carbon::parse($fechaInicio)->startOfDay();

        for ($i = 0; $i < $cuotas; $i++) {
            $fechas[] = $fecha->toDateString();
            $fecha = $this->siguienteFechaQuincenal($fecha);
        }

        return $fechas;
    }

    /**
     * Siguiente fecha quincenal anclada a día 15 y fin de mes.
     * - Antes del 15 → día 15 del mismo mes
     * - El día 15 → fin de mes
     * - Después del 15 → día 15 del mes siguiente
     */
    public function siguienteFechaQuincenal(Carbon $fecha): Carbon
    {
        $siguiente = $fecha->copy()->startOfDay();

        if ($siguiente->day < 15) {
            return $siguiente->day(15);
        }

        if ($siguiente->day === 15) {
            return $siguiente->endOfMonth()->startOfDay();
        }

        return $siguiente->addMonthNoOverflow()->day(15)->startOfDay();
    }

    /**
     * Distribuye el monto total en N cuotas (ajusta la última por redondeo).
     *
     * @return array<int, float>
     */
    public function distribuirMontos(float $montoTotal, int $cuotas): array
    {
        $montoCuota = round($montoTotal / $cuotas, 2);
        $montos = [];

        for ($i = 0; $i < $cuotas; $i++) {
            if ($i === $cuotas - 1) {
                $montos[] = round($montoTotal - ($montoCuota * ($cuotas - 1)), 2);
            } else {
                $montos[] = $montoCuota;
            }
        }

        return $montos;
    }

    /**
     * Crea las transacciones de cuotas de uniforme.
     *
     * @param  array  $data  personal_id, monto, descripcion, fecha_inicio, cuotas_totales,
     *                       cuotas? (array de fecha_transaccion/monto), asistencia_id?, registrado_por_user_id?
     * @return array{grupo_uniforme: string, transacciones: \Illuminate\Support\Collection}
     */
    public function crearDescuentoUniforme(array $data): array
    {
        $cuotasTotales = (int) ($data['cuotas_totales'] ?? 1);
        $montoTotal = round((float) $data['monto'], 2);
        $fechaInicio = $data['fecha_inicio'] ?? $data['fecha_transaccion'] ?? now()->toDateString();
        $descripcionBase = trim((string) ($data['descripcion'] ?? 'Descuento uniforme quincenal'));
        $grupo = (string) Str::uuid();

        $cuotasInput = $data['cuotas'] ?? null;

        if (is_array($cuotasInput) && count($cuotasInput) > 0) {
            $cuotasTotales = count($cuotasInput);
            $fechas = array_map(fn ($c) => $c['fecha_transaccion'], $cuotasInput);
            $montos = array_map(fn ($c) => round((float) $c['monto'], 2), $cuotasInput);
            $montoTotal = round(array_sum($montos), 2);
        } else {
            $fechas = $this->generarFechasQuincenales($fechaInicio, $cuotasTotales);
            $montos = $this->distribuirMontos($montoTotal, $cuotasTotales);
        }

        $transacciones = DB::transaction(function () use (
            $data, $grupo, $cuotasTotales, $montoTotal, $descripcionBase, $fechas, $montos
        ) {
            $creadas = collect();
            $acumulado = 0.0;

            for ($i = 0; $i < $cuotasTotales; $i++) {
                $montoCuota = $montos[$i];
                $acumulado += $montoCuota;
                $saldoDespues = round($montoTotal - $acumulado, 2);
                $numero = $i + 1;

                $transaccion = Transaccion::create([
                    'personal_id' => $data['personal_id'],
                    'asistencia_id' => $data['asistencia_id'] ?? null,
                    'tipo_transaccion' => 'uniforme',
                    'monto' => $montoCuota,
                    'descripcion' => "Cuota {$numero} de {$cuotasTotales} - {$descripcionBase}",
                    'fecha_transaccion' => $fechas[$i],
                    'es_descuento' => true,
                    'estado_transaccion' => 'pendiente',
                    'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
                    'grupo_uniforme' => $grupo,
                    'numero_cuota' => $numero,
                    'cuotas_totales' => $cuotasTotales,
                    'monto_total_uniforme' => $montoTotal,
                    'saldo_despues' => $saldoDespues,
                ]);

                $creadas->push($transaccion);
            }

            return $creadas;
        });

        return [
            'grupo_uniforme' => $grupo,
            'transacciones' => $transacciones,
        ];
    }

    /**
     * Obtiene el desglose de un grupo de uniforme ordenado por cuota.
     */
    public function obtenerDesgloseGrupo(string $grupoUniforme)
    {
        return Transaccion::where('grupo_uniforme', $grupoUniforme)
            ->orderBy('numero_cuota')
            ->get();
    }

    /**
     * Actualiza la fecha de una cuota pendiente de uniforme.
     */
    public function actualizarFechaCuota(Transaccion $transaccion, string $nuevaFecha): Transaccion
    {
        if ($transaccion->tipo_transaccion !== 'uniforme') {
            throw new \InvalidArgumentException('Solo se pueden editar fechas de cuotas de uniforme.');
        }

        if ($transaccion->estado_transaccion !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se pueden editar fechas de cuotas pendientes.');
        }

        $transaccion->update([
            'fecha_transaccion' => $nuevaFecha,
        ]);

        return $transaccion->fresh(['personal', 'prestamo', 'asistencia', 'registradoPor']);
    }

    /**
     * Actualiza en lote las fechas de cuotas pendientes de un grupo.
     *
     * @param  array<int, array{id:int, fecha_transaccion:string}>  $cuotas
     * @return \Illuminate\Support\Collection
     */
    public function actualizarFechasPendientes(string $grupoUniforme, array $cuotas)
    {
        $pendientes = Transaccion::where('grupo_uniforme', $grupoUniforme)
            ->where('tipo_transaccion', 'uniforme')
            ->where('estado_transaccion', 'pendiente')
            ->get()
            ->keyBy('id');

        if ($pendientes->isEmpty()) {
            throw new \InvalidArgumentException('No hay cuotas pendientes para actualizar en este grupo.');
        }

        return DB::transaction(function () use ($cuotas, $pendientes) {
            $actualizadas = collect();

            foreach ($cuotas as $item) {
                $id = (int) ($item['id'] ?? 0);
                $fecha = $item['fecha_transaccion'] ?? null;

                if (!$id || !$pendientes->has($id)) {
                    continue;
                }

                $transaccion = $pendientes->get($id);
                $payload = [];
                if ($fecha) {
                    $payload['fecha_transaccion'] = $fecha;
                }
                if (array_key_exists('monto', $item) && $item['monto'] !== null && $item['monto'] !== '') {
                    $monto = round((float) $item['monto'], 2);
                    if ($monto < 0.01) {
                        throw new \InvalidArgumentException('El monto de cada cuota debe ser mayor a 0.');
                    }
                    $payload['monto'] = $monto;
                }
                if ($payload === []) {
                    continue;
                }
                $transaccion->update($payload);
                $actualizadas->push($transaccion->fresh());
            }

            if ($actualizadas->isEmpty()) {
                throw new \InvalidArgumentException('No se actualizó ninguna cuota pendiente.');
            }

            $this->recalcularSaldosGrupo($grupoUniforme);

            return $actualizadas->map->fresh();
        });
    }

    /**
     * Recalcula saldo_despues de todas las cuotas del grupo (ordenadas).
     */
    public function recalcularSaldosGrupo(string $grupoUniforme): void
    {
        $cuotas = Transaccion::where('grupo_uniforme', $grupoUniforme)
            ->where('tipo_transaccion', 'uniforme')
            ->orderBy('numero_cuota')
            ->get();

        if ($cuotas->isEmpty()) {
            return;
        }

        $montoTotal = (float) $cuotas->sum('monto');
        $acumulado = 0.0;

        foreach ($cuotas as $cuota) {
            $acumulado += (float) $cuota->monto;
            $cuota->update([
                'saldo_despues' => round($montoTotal - $acumulado, 2),
            ]);
        }
    }

    /**
     * Reprograma las cuotas pendientes de un grupo desde una fecha de inicio,
     * generando fechas quincenales. Las ya aplicadas/canceladas no se tocan.
     *
     * @return \Illuminate\Support\Collection
     */
    public function reprogramarFechasPendientes(string $grupoUniforme, string $fechaInicio)
    {
        $pendientes = Transaccion::where('grupo_uniforme', $grupoUniforme)
            ->where('tipo_transaccion', 'uniforme')
            ->where('estado_transaccion', 'pendiente')
            ->orderBy('numero_cuota')
            ->get();

        if ($pendientes->isEmpty()) {
            throw new \InvalidArgumentException('No hay cuotas pendientes para reprogramar.');
        }

        $fechas = $this->generarFechasQuincenales($fechaInicio, $pendientes->count());

        return DB::transaction(function () use ($pendientes, $fechas) {
            $actualizadas = collect();

            foreach ($pendientes->values() as $index => $transaccion) {
                $transaccion->update([
                    'fecha_transaccion' => $fechas[$index],
                ]);
                $actualizadas->push($transaccion->fresh());
            }

            return $actualizadas;
        });
    }

    /**
     * Cambia el número total de cuotas del plan.
     * Conserva las ya aplicadas; redistribuye el saldo pendiente en las nuevas cuotas restantes.
     *
     * @param  string  $grupoUniforme
     * @param  int  $nuevoTotal  Total de cuotas del plan (incluye las ya aplicadas)
     * @param  string|null  $fechaInicio  Fecha de la próxima cuota pendiente
     * @param  int|null  $userId
     * @return \Illuminate\Support\Collection
     */
    public function cambiarNumeroCuotas(
        string $grupoUniforme,
        int $nuevoTotal,
        ?string $fechaInicio = null,
        ?int $userId = null
    ) {
        if ($nuevoTotal < 1 || $nuevoTotal > 60) {
            throw new \InvalidArgumentException('El número de cuotas debe estar entre 1 y 60.');
        }

        $cuotas = $this->obtenerDesgloseGrupo($grupoUniforme);

        if ($cuotas->isEmpty()) {
            throw new \InvalidArgumentException('No se encontró el grupo de uniforme.');
        }

        $aplicadas = $cuotas->where('estado_transaccion', 'aplicado')->sortBy('numero_cuota')->values();
        $pendientes = $cuotas->where('estado_transaccion', 'pendiente')->sortBy('numero_cuota')->values();
        $aplicadasCount = $aplicadas->count();

        if ($nuevoTotal < $aplicadasCount) {
            throw new \InvalidArgumentException(
                "No se puede dejar el plan en {$nuevoTotal} cuotas porque ya hay {$aplicadasCount} aplicada(s)."
            );
        }

        $nuevasPendientes = $nuevoTotal - $aplicadasCount;
        $saldoPendiente = round((float) $pendientes->sum('monto'), 2);
        $montoTotal = round((float) ($cuotas->first()->monto_total_uniforme ?? (
            $aplicadas->sum('monto') + $saldoPendiente
        )), 2);

        if ($nuevasPendientes === 0) {
            if ($saldoPendiente > 0.009) {
                throw new \InvalidArgumentException(
                    'Aún hay saldo pendiente. El número de cuotas debe dejar al menos 1 cuota pendiente para cubrirlo.'
                );
            }
        }

        if ($nuevasPendientes > 0 && $saldoPendiente <= 0) {
            throw new \InvalidArgumentException('No hay saldo pendiente para redistribuir en nuevas cuotas.');
        }

        // Si el total no cambia y la cantidad de pendientes es la misma, no hace falta recrear
        if ((int) ($cuotas->first()->cuotas_totales ?? 0) === $nuevoTotal
            && $pendientes->count() === $nuevasPendientes
        ) {
            throw new \InvalidArgumentException('El plan ya tiene ese número de cuotas.');
        }

        $primera = $cuotas->first();
        $descripcionBase = (string) $primera->descripcion;
        if (preg_match('/^Cuota\s+\d+\s+de\s+\d+\s+-\s+(.+)$/u', $descripcionBase, $m)) {
            $descripcionBase = $m[1];
        }

        $rawFechaPendiente = optional($pendientes->first())->fecha_transaccion;
        $fechaInicio = $fechaInicio
            ?: ($rawFechaPendiente ? Carbon::parse($rawFechaPendiente)->toDateString() : now()->toDateString());

        return DB::transaction(function () use (
            $grupoUniforme,
            $nuevoTotal,
            $aplicadas,
            $pendientes,
            $nuevasPendientes,
            $saldoPendiente,
            $montoTotal,
            $descripcionBase,
            $fechaInicio,
            $primera,
            $userId
        ) {
            // Eliminar pendientes actuales (se recrean redistribuidas)
            foreach ($pendientes as $pendiente) {
                $pendiente->delete();
            }

            $montosPagados = 0.0;
            $numero = 0;

            // Actualizar metadatos de las aplicadas
            foreach ($aplicadas as $aplicada) {
                $numero++;
                $montosPagados = round($montosPagados + (float) $aplicada->monto, 2);
                $aplicada->update([
                    'numero_cuota' => $numero,
                    'cuotas_totales' => $nuevoTotal,
                    'monto_total_uniforme' => $montoTotal,
                    'saldo_despues' => round($montoTotal - $montosPagados, 2),
                    'descripcion' => "Cuota {$numero} de {$nuevoTotal} - {$descripcionBase}",
                ]);
            }

            $creadas = collect();

            if ($nuevasPendientes > 0) {
                $montos = $this->distribuirMontos($saldoPendiente, $nuevasPendientes);
                $fechas = $this->generarFechasQuincenales($fechaInicio, $nuevasPendientes);
                $acumuladoPendiente = 0.0;

                for ($i = 0; $i < $nuevasPendientes; $i++) {
                    $numero++;
                    $acumuladoPendiente = round($acumuladoPendiente + $montos[$i], 2);
                    $saldoDespues = round($montoTotal - $montosPagados - $acumuladoPendiente, 2);

                    $creadas->push(Transaccion::create([
                        'personal_id' => $primera->personal_id,
                        'asistencia_id' => null,
                        'tipo_transaccion' => 'uniforme',
                        'monto' => $montos[$i],
                        'descripcion' => "Cuota {$numero} de {$nuevoTotal} - {$descripcionBase}",
                        'fecha_transaccion' => $fechas[$i],
                        'es_descuento' => true,
                        'estado_transaccion' => 'pendiente',
                        'registrado_por_user_id' => $userId ?? $primera->registrado_por_user_id,
                        'grupo_uniforme' => $grupoUniforme,
                        'numero_cuota' => $numero,
                        'cuotas_totales' => $nuevoTotal,
                        'monto_total_uniforme' => $montoTotal,
                        'saldo_despues' => $saldoDespues,
                    ]));
                }
            }

            // Actualizar también canceladas del grupo (solo metadatos, no montos)
            Transaccion::where('grupo_uniforme', $grupoUniforme)
                ->where('estado_transaccion', 'cancelado')
                ->update([
                    'cuotas_totales' => $nuevoTotal,
                    'monto_total_uniforme' => $montoTotal,
                ]);

            return $this->obtenerDesgloseGrupo($grupoUniforme);
        });
    }

    /**
     * Resumen de grupos de uniforme de un personal (para listar planes activos).
     */
    public function listarGruposPorPersonal(int $personalId)
    {
        $grupos = Transaccion::where('personal_id', $personalId)
            ->where('tipo_transaccion', 'uniforme')
            ->whereNotNull('grupo_uniforme')
            ->select('grupo_uniforme')
            ->distinct()
            ->pluck('grupo_uniforme');

        return $grupos->map(function ($grupo) {
            $cuotas = $this->obtenerDesgloseGrupo($grupo);
            if ($cuotas->isEmpty()) {
                return null;
            }

            $pendientes = $cuotas->where('estado_transaccion', 'pendiente');
            $aplicadas = $cuotas->where('estado_transaccion', 'aplicado');
            $primera = $cuotas->first();
            $descripcion = (string) $primera->descripcion;
            if (preg_match('/^Cuota\s+\d+\s+de\s+\d+\s+-\s+(.+)$/u', $descripcion, $m)) {
                $descripcion = $m[1];
            }

            return [
                'grupo_uniforme' => $grupo,
                'descripcion' => $descripcion,
                'monto_total' => (float) ($primera->monto_total_uniforme ?? $cuotas->sum('monto')),
                'saldo_pendiente' => (float) $pendientes->sum('monto'),
                'monto_aplicado' => (float) $aplicadas->sum('monto'),
                'cuotas_totales' => (int) ($primera->cuotas_totales ?? $cuotas->count()),
                'cuotas_aplicadas' => $aplicadas->count(),
                'cuotas_pendientes' => $pendientes->count(),
                'proxima_fecha' => optional($pendientes->sortBy('fecha_transaccion')->first())->fecha_transaccion,
                'activo' => $pendientes->isNotEmpty(),
                'cuotas' => $cuotas,
            ];
        })->filter()->values();
    }
}
