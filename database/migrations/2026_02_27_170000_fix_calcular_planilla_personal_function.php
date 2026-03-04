<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Corrige la función calcular_planilla_personal:
     * - oa.fecha → oa.fecha_asistencia
     * - oa.estado_asistencia → usar columnas reales (es_descanso, es_ausente, hora_entrada)
     * - Soporta asistencia directa (personal_id) además de asignación
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION calcular_planilla_personal(
                p_personal_id BIGINT,
                p_periodo_inicio DATE,
                p_periodo_fin DATE
            ) RETURNS TABLE (
                personal_id BIGINT,
                proyecto_id BIGINT,
                dias_trabajados INTEGER,
                horas_trabajadas NUMERIC(8,2),
                pago_por_hora NUMERIC(10,2),
                salario_devengado NUMERIC(10,2),
                descuento_multas NUMERIC(10,2),
                descuento_uniformes NUMERIC(10,2),
                descuento_anticipos NUMERIC(10,2),
                descuento_prestamos NUMERIC(10,2),
                descuento_antecedentes NUMERIC(10,2),
                otros_descuentos NUMERIC(10,2),
                total_descuentos NUMERIC(10,2),
                salario_neto NUMERIC(10,2)
            ) AS \$\$
            DECLARE
                v_dias_trabajados INTEGER;
                v_horas_trabajadas NUMERIC(8,2);
                v_pago_por_hora NUMERIC(10,2);
                v_salario_devengado NUMERIC(10,2);
                v_descuento_multas NUMERIC(10,2);
                v_descuento_uniformes NUMERIC(10,2);
                v_descuento_anticipos NUMERIC(10,2);
                v_descuento_prestamos NUMERIC(10,2);
                v_descuento_antecedentes NUMERIC(10,2);
                v_otros_descuentos NUMERIC(10,2);
                v_total_descuentos NUMERIC(10,2);
                v_salario_neto NUMERIC(10,2);
                v_proyecto_id BIGINT;
            BEGIN
                -- Obtener proyecto activo del personal y su pago por hora
                SELECT opa.proyecto_id, pcp.pago_hora_personal
                INTO v_proyecto_id, v_pago_por_hora
                FROM operaciones_personal_asignado opa
                INNER JOIN proyectos_configuracion_personal pcp ON opa.configuracion_puesto_id = pcp.id
                WHERE opa.personal_id = p_personal_id
                  AND opa.estado_asignacion = 'activa'
                  AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= p_periodo_fin)
                ORDER BY opa.fecha_inicio DESC
                LIMIT 1;

                -- Si no tiene proyecto activo, usar salario base dividido entre 240 horas mensuales
                IF v_pago_por_hora IS NULL THEN
                    SELECT salario_base / 240
                    INTO v_pago_por_hora
                    FROM personal
                    WHERE id = p_personal_id;

                    -- Si aún es NULL, usar 0
                    v_pago_por_hora := COALESCE(v_pago_por_hora, 0);
                END IF;

                -- Calcular días trabajados (presente o tarde)
                -- Considera tanto asistencia por asignación como asistencia directa
                SELECT COUNT(DISTINCT oa.fecha_asistencia)
                INTO v_dias_trabajados
                FROM operaciones_asistencia oa
                LEFT JOIN operaciones_personal_asignado opa ON oa.personal_asignado_id = opa.id
                WHERE (
                    -- Asistencia con asignación
                    (opa.personal_id = p_personal_id)
                    OR
                    -- Asistencia directa (sin asignación)
                    (oa.personal_id = p_personal_id AND oa.personal_asignado_id IS NULL)
                )
                  AND oa.fecha_asistencia BETWEEN p_periodo_inicio AND p_periodo_fin
                  -- Presente o tarde: no es descanso, no es ausente, tiene hora de entrada
                  AND oa.es_descanso = false
                  AND COALESCE(oa.es_ausente, false) = false
                  AND oa.hora_entrada IS NOT NULL;

                -- Si no hay registros, usar 0
                v_dias_trabajados := COALESCE(v_dias_trabajados, 0);

                -- Calcular horas trabajadas (8 horas por día por defecto)
                -- TODO: Se podría mejorar sumando las horas reales de cada registro
                v_horas_trabajadas := v_dias_trabajados * 8;

                -- Calcular salario devengado
                v_salario_devengado := v_horas_trabajadas * v_pago_por_hora;

                -- Calcular descuentos por tipo de transacción (solo pendientes)
                SELECT
                    COALESCE(SUM(CASE WHEN ot.tipo_transaccion = 'multa' THEN ot.monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN ot.tipo_transaccion = 'uniforme' THEN ot.monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN ot.tipo_transaccion = 'anticipo' THEN ot.monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN ot.tipo_transaccion = 'abono_prestamo' THEN ot.monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN ot.tipo_transaccion = 'antecedentes' THEN ot.monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN ot.tipo_transaccion = 'otro_descuento' THEN ot.monto ELSE 0 END), 0)
                INTO
                    v_descuento_multas,
                    v_descuento_uniformes,
                    v_descuento_anticipos,
                    v_descuento_prestamos,
                    v_descuento_antecedentes,
                    v_otros_descuentos
                FROM operaciones_transacciones ot
                WHERE ot.personal_id = p_personal_id
                  AND ot.fecha_transaccion BETWEEN p_periodo_inicio AND p_periodo_fin
                  AND ot.estado_transaccion = 'pendiente'
                  AND ot.es_descuento = true;

                -- Total descuentos
                v_total_descuentos := v_descuento_multas + v_descuento_uniformes + v_descuento_anticipos +
                                      v_descuento_prestamos + v_descuento_antecedentes + v_otros_descuentos;

                -- Salario neto (no puede ser negativo)
                v_salario_neto := GREATEST(v_salario_devengado - v_total_descuentos, 0);

                -- Retornar resultado
                RETURN QUERY SELECT
                    p_personal_id,
                    v_proyecto_id,
                    v_dias_trabajados,
                    v_horas_trabajadas,
                    v_pago_por_hora,
                    v_salario_devengado,
                    v_descuento_multas,
                    v_descuento_uniformes,
                    v_descuento_anticipos,
                    v_descuento_prestamos,
                    v_descuento_antecedentes,
                    v_otros_descuentos,
                    v_total_descuentos,
                    v_salario_neto;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar la función original (con errores)
        DB::statement("
            CREATE OR REPLACE FUNCTION calcular_planilla_personal(
                p_personal_id BIGINT,
                p_periodo_inicio DATE,
                p_periodo_fin DATE
            ) RETURNS TABLE (
                personal_id BIGINT,
                proyecto_id BIGINT,
                dias_trabajados INTEGER,
                horas_trabajadas NUMERIC(8,2),
                pago_por_hora NUMERIC(10,2),
                salario_devengado NUMERIC(10,2),
                descuento_multas NUMERIC(10,2),
                descuento_uniformes NUMERIC(10,2),
                descuento_anticipos NUMERIC(10,2),
                descuento_prestamos NUMERIC(10,2),
                descuento_antecedentes NUMERIC(10,2),
                otros_descuentos NUMERIC(10,2),
                total_descuentos NUMERIC(10,2),
                salario_neto NUMERIC(10,2)
            ) AS \$\$
            DECLARE
                v_dias_trabajados INTEGER;
                v_horas_trabajadas NUMERIC(8,2);
                v_pago_por_hora NUMERIC(10,2);
                v_salario_devengado NUMERIC(10,2);
                v_descuento_multas NUMERIC(10,2);
                v_descuento_uniformes NUMERIC(10,2);
                v_descuento_anticipos NUMERIC(10,2);
                v_descuento_prestamos NUMERIC(10,2);
                v_descuento_antecedentes NUMERIC(10,2);
                v_otros_descuentos NUMERIC(10,2);
                v_total_descuentos NUMERIC(10,2);
                v_salario_neto NUMERIC(10,2);
                v_proyecto_id BIGINT;
            BEGIN
                SELECT opa.proyecto_id, pcp.pago_hora_personal
                INTO v_proyecto_id, v_pago_por_hora
                FROM operaciones_personal_asignado opa
                INNER JOIN proyectos_configuracion_personal pcp ON opa.configuracion_puesto_id = pcp.id
                WHERE opa.personal_id = p_personal_id
                  AND opa.estado_asignacion = 'activa'
                  AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= p_periodo_fin)
                ORDER BY opa.fecha_inicio DESC
                LIMIT 1;

                IF v_pago_por_hora IS NULL THEN
                    SELECT salario_base / 240
                    INTO v_pago_por_hora
                    FROM personal
                    WHERE id = p_personal_id;
                    v_pago_por_hora := COALESCE(v_pago_por_hora, 0);
                END IF;

                SELECT COUNT(DISTINCT oa.fecha)
                INTO v_dias_trabajados
                FROM operaciones_asistencia oa
                INNER JOIN operaciones_personal_asignado opa ON oa.personal_asignado_id = opa.id
                WHERE opa.personal_id = p_personal_id
                  AND oa.fecha BETWEEN p_periodo_inicio AND p_periodo_fin
                  AND oa.estado_asistencia IN ('presente', 'tarde');

                v_dias_trabajados := COALESCE(v_dias_trabajados, 0);
                v_horas_trabajadas := v_dias_trabajados * 8;
                v_salario_devengado := v_horas_trabajadas * v_pago_por_hora;

                SELECT
                    COALESCE(SUM(CASE WHEN tipo_transaccion = 'multa' THEN monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN tipo_transaccion = 'uniforme' THEN monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN tipo_transaccion = 'anticipo' THEN monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN tipo_transaccion = 'abono_prestamo' THEN monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN tipo_transaccion = 'antecedentes' THEN monto ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN tipo_transaccion = 'otro_descuento' THEN monto ELSE 0 END), 0)
                INTO
                    v_descuento_multas,
                    v_descuento_uniformes,
                    v_descuento_anticipos,
                    v_descuento_prestamos,
                    v_descuento_antecedentes,
                    v_otros_descuentos
                FROM operaciones_transacciones
                WHERE personal_id = p_personal_id
                  AND fecha_transaccion BETWEEN p_periodo_inicio AND p_periodo_fin
                  AND estado_transaccion = 'pendiente'
                  AND es_descuento = true;

                v_total_descuentos := v_descuento_multas + v_descuento_uniformes + v_descuento_anticipos +
                                      v_descuento_prestamos + v_descuento_antecedentes + v_otros_descuentos;
                v_salario_neto := GREATEST(v_salario_devengado - v_total_descuentos, 0);

                RETURN QUERY SELECT
                    p_personal_id,
                    v_proyecto_id,
                    v_dias_trabajados,
                    v_horas_trabajadas,
                    v_pago_por_hora,
                    v_salario_devengado,
                    v_descuento_multas,
                    v_descuento_uniformes,
                    v_descuento_anticipos,
                    v_descuento_prestamos,
                    v_descuento_antecedentes,
                    v_otros_descuentos,
                    v_total_descuentos,
                    v_salario_neto;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }
};
