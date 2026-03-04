<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Simplifica las funciones de validación de asistencia.
     * La lógica de normalización ahora está en el servicio PHP.
     * El trigger solo valida coherencia.
     */
    public function up(): void
    {
        // Actualizar validar_asistencia (función principal usada por el trigger)
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_asistencia()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_asignacion_activa BOOLEAN;
                v_fecha_inicio DATE;
                v_fecha_fin DATE;
                v_personal_activo BOOLEAN;
            BEGIN
                -- CASO 1: Asistencia con asignación (comportamiento original)
                IF NEW.personal_asignado_id IS NOT NULL THEN
                    -- Verificar que la asignación exista
                    SELECT
                        estado_asignacion = 'activa',
                        fecha_inicio,
                        fecha_fin
                    INTO v_asignacion_activa, v_fecha_inicio, v_fecha_fin
                    FROM operaciones_personal_asignado
                    WHERE id = NEW.personal_asignado_id;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'La asignación no existe'
                        USING ERRCODE = 'P0011';
                    END IF;

                    -- Verificar que la fecha esté dentro del rango de la asignación
                    IF NEW.fecha_asistencia < v_fecha_inicio THEN
                        RAISE EXCEPTION 'La fecha de asistencia es anterior al inicio de la asignación'
                        USING ERRCODE = 'P0012';
                    END IF;

                    IF v_fecha_fin IS NOT NULL AND NEW.fecha_asistencia > v_fecha_fin THEN
                        RAISE EXCEPTION 'La fecha de asistencia es posterior al fin de la asignación'
                        USING ERRCODE = 'P0013';
                    END IF;

                -- CASO 2: Asistencia directa a personal sin asignación
                ELSIF NEW.personal_id IS NOT NULL THEN
                    -- Verificar que el personal exista y esté activo
                    SELECT estado = 'activo'
                    INTO v_personal_activo
                    FROM personal
                    WHERE id = NEW.personal_id AND deleted_at IS NULL;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'El personal no existe o está eliminado'
                        USING ERRCODE = 'P0017';
                    END IF;

                    IF NOT v_personal_activo THEN
                        RAISE EXCEPTION 'El personal no está activo'
                        USING ERRCODE = 'P0018';
                    END IF;
                END IF;

                -- Validar que si hay hora_salida, también haya hora_entrada
                IF NEW.hora_salida IS NOT NULL AND NEW.hora_entrada IS NULL THEN
                    RAISE EXCEPTION 'No puede registrar hora de salida sin hora de entrada'
                    USING ERRCODE = 'P0014';
                END IF;

                -- Validar coherencia reemplazo (solo si fue_reemplazado está activo)
                IF NEW.fue_reemplazado = TRUE AND NEW.personal_reemplazo_id IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el personal de reemplazo'
                    USING ERRCODE = 'P0015';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Actualizar también validar_coherencia_asistencia
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_coherencia_asistencia()
            RETURNS TRIGGER AS \$\$
            BEGIN
                -- Validar que si hay hora_salida, también haya hora_entrada
                IF NEW.hora_salida IS NOT NULL AND NEW.hora_entrada IS NULL THEN
                    RAISE EXCEPTION 'No puede registrar hora de salida sin hora de entrada'
                    USING ERRCODE = 'P0014';
                END IF;

                -- Validar coherencia reemplazo
                IF NEW.fue_reemplazado = TRUE AND NEW.personal_reemplazo_id IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el personal de reemplazo'
                    USING ERRCODE = 'P0015';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Revertir al comportamiento anterior.
     */
    public function down(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_asistencia()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_asignacion_activa BOOLEAN;
                v_fecha_inicio DATE;
                v_fecha_fin DATE;
                v_personal_activo BOOLEAN;
            BEGIN
                IF NEW.personal_asignado_id IS NOT NULL THEN
                    SELECT
                        estado_asignacion = 'activa',
                        fecha_inicio,
                        fecha_fin
                    INTO v_asignacion_activa, v_fecha_inicio, v_fecha_fin
                    FROM operaciones_personal_asignado
                    WHERE id = NEW.personal_asignado_id;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'La asignación no existe'
                        USING ERRCODE = 'P0011';
                    END IF;

                    IF NEW.fecha_asistencia < v_fecha_inicio THEN
                        RAISE EXCEPTION 'La fecha de asistencia es anterior al inicio de la asignación'
                        USING ERRCODE = 'P0012';
                    END IF;

                    IF v_fecha_fin IS NOT NULL AND NEW.fecha_asistencia > v_fecha_fin THEN
                        RAISE EXCEPTION 'La fecha de asistencia es posterior al fin de la asignación'
                        USING ERRCODE = 'P0013';
                    END IF;

                ELSIF NEW.personal_id IS NOT NULL THEN
                    SELECT estado = 'activo'
                    INTO v_personal_activo
                    FROM personal
                    WHERE id = NEW.personal_id AND deleted_at IS NULL;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'El personal no existe o está eliminado'
                        USING ERRCODE = 'P0017';
                    END IF;

                    IF NOT v_personal_activo THEN
                        RAISE EXCEPTION 'El personal no está activo'
                        USING ERRCODE = 'P0018';
                    END IF;
                END IF;

                IF NEW.es_descanso = TRUE THEN
                    NEW.hora_entrada := NULL;
                    NEW.hora_salida := NULL;
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
                END IF;

                IF NEW.hora_salida IS NOT NULL AND NEW.hora_entrada IS NULL THEN
                    RAISE EXCEPTION 'No puede registrar hora de salida sin hora de entrada'
                    USING ERRCODE = 'P0014';
                END IF;

                IF NEW.fue_reemplazado = TRUE AND NEW.personal_reemplazo_id IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el personal de reemplazo'
                    USING ERRCODE = 'P0015';
                END IF;

                IF NEW.fue_reemplazado = TRUE AND NEW.motivo_reemplazo IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el motivo del reemplazo'
                    USING ERRCODE = 'P0016';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_coherencia_asistencia()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NEW.es_descanso = TRUE THEN
                    NEW.hora_entrada := NULL;
                    NEW.hora_salida := NULL;
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
                END IF;

                IF NEW.hora_salida IS NOT NULL AND NEW.hora_entrada IS NULL THEN
                    RAISE EXCEPTION 'No puede registrar hora de salida sin hora de entrada'
                    USING ERRCODE = 'P0014';
                END IF;

                IF NEW.fue_reemplazado = TRUE AND NEW.personal_reemplazo_id IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el personal de reemplazo'
                    USING ERRCODE = 'P0015';
                END IF;

                IF NEW.fue_reemplazado = TRUE AND NEW.motivo_reemplazo IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el motivo del reemplazo'
                    USING ERRCODE = 'P0016';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }
};
