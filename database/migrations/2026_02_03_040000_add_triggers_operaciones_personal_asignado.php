<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // =====================================================
        // TRIGGER 1: Validar que no haya conflicto de asignaciones
        // Un empleado no puede estar asignado a dos proyectos
        // en el mismo rango de fechas
        // =====================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_asignacion_sin_conflicto()
            RETURNS TRIGGER AS \$\$
            DECLARE
                conflicto_count INTEGER;
                proyecto_conflicto VARCHAR(255);
            BEGIN
                -- Solo validar si la asignación está activa
                IF NEW.estado_asignacion != 'activa' THEN
                    RETURN NEW;
                END IF;

                -- Buscar asignaciones que se solapen
                SELECT COUNT(*), MAX(p.nombre_proyecto)
                INTO conflicto_count, proyecto_conflicto
                FROM operaciones_personal_asignado opa
                INNER JOIN proyectos p ON p.id = opa.proyecto_id
                WHERE opa.personal_id = NEW.personal_id
                  AND opa.estado_asignacion = 'activa'
                  AND opa.id != COALESCE(NEW.id, 0)
                  AND (
                    -- Caso 1: fecha_fin es NULL (asignación indefinida)
                    (opa.fecha_fin IS NULL AND (
                        NEW.fecha_fin IS NULL OR NEW.fecha_fin >= opa.fecha_inicio
                    ))
                    OR
                    -- Caso 2: fecha_fin tiene valor - verificar solapamiento
                    (opa.fecha_fin IS NOT NULL AND (
                        (NEW.fecha_fin IS NULL AND NEW.fecha_inicio <= opa.fecha_fin)
                        OR
                        (NEW.fecha_fin IS NOT NULL AND
                         NEW.fecha_inicio <= opa.fecha_fin AND
                         NEW.fecha_fin >= opa.fecha_inicio)
                    ))
                  );

                IF conflicto_count > 0 THEN
                    RAISE EXCEPTION 'El personal ya tiene una asignación activa que se solapa con estas fechas. Proyecto en conflicto: %', proyecto_conflicto
                    USING ERRCODE = 'P0001';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_validar_asignacion_sin_conflicto
            BEFORE INSERT OR UPDATE ON operaciones_personal_asignado
            FOR EACH ROW
            EXECUTE FUNCTION validar_asignacion_sin_conflicto();
        ");

        // =====================================================
        // TRIGGER 2: Validar requisitos del puesto
        // Verificar edad, sexo, altura del personal vs configuración
        // =====================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_requisitos_puesto()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_personal RECORD;
                v_config RECORD;
                v_edad INTEGER;
                v_errores TEXT[];
            BEGIN
                -- Obtener datos del personal
                SELECT p.*,
                       EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::INTEGER as edad
                INTO v_personal
                FROM personal p
                WHERE p.id = NEW.personal_id AND p.deleted_at IS NULL;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Personal no encontrado o está eliminado'
                    USING ERRCODE = 'P0002';
                END IF;

                -- Verificar que el personal esté activo
                IF v_personal.estado != 'activo' THEN
                    RAISE EXCEPTION 'El personal no está activo. Estado actual: %', v_personal.estado
                    USING ERRCODE = 'P0003';
                END IF;

                -- Obtener configuración del puesto
                SELECT *
                INTO v_config
                FROM proyectos_configuracion_personal
                WHERE id = NEW.configuracion_puesto_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Configuración de puesto no encontrada'
                    USING ERRCODE = 'P0004';
                END IF;

                -- Verificar que la configuración pertenezca al proyecto
                IF v_config.proyecto_id != NEW.proyecto_id THEN
                    RAISE EXCEPTION 'La configuración de puesto no pertenece al proyecto especificado'
                    USING ERRCODE = 'P0005';
                END IF;

                -- Verificar que la configuración esté activa
                IF v_config.estado != 'activo' THEN
                    RAISE EXCEPTION 'La configuración de puesto no está activa'
                    USING ERRCODE = 'P0006';
                END IF;

                v_errores := ARRAY[]::TEXT[];

                -- Validar edad
                IF v_personal.edad < v_config.edad_minima THEN
                    v_errores := array_append(v_errores,
                        format('Edad (%s años) menor al mínimo requerido (%s años)',
                               v_personal.edad, v_config.edad_minima));
                END IF;

                IF v_personal.edad > v_config.edad_maxima THEN
                    v_errores := array_append(v_errores,
                        format('Edad (%s años) mayor al máximo permitido (%s años)',
                               v_personal.edad, v_config.edad_maxima));
                END IF;

                -- Validar sexo (si está especificado)
                IF v_config.sexo_id IS NOT NULL AND v_personal.sexo_id != v_config.sexo_id THEN
                    v_errores := array_append(v_errores, 'El sexo no coincide con el requerido para el puesto');
                END IF;

                -- Validar altura (si está especificada)
                IF v_config.altura_minima IS NOT NULL AND v_personal.altura < v_config.altura_minima THEN
                    v_errores := array_append(v_errores,
                        format('Altura (%s m) menor a la requerida (%s m)',
                               v_personal.altura, v_config.altura_minima));
                END IF;

                -- Si hay errores, lanzar excepción
                IF array_length(v_errores, 1) > 0 THEN
                    RAISE EXCEPTION 'El personal no cumple los requisitos del puesto: %',
                        array_to_string(v_errores, '; ')
                    USING ERRCODE = 'P0007';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_validar_requisitos_puesto
            BEFORE INSERT OR UPDATE ON operaciones_personal_asignado
            FOR EACH ROW
            EXECUTE FUNCTION validar_requisitos_puesto();
        ");

        // =====================================================
        // TRIGGER 3: Validar capacidad del puesto
        // No exceder la cantidad_requerida del puesto
        // =====================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_capacidad_puesto()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_cantidad_requerida INTEGER;
                v_cantidad_asignada INTEGER;
            BEGIN
                -- Solo validar para asignaciones activas
                IF NEW.estado_asignacion != 'activa' THEN
                    RETURN NEW;
                END IF;

                -- Obtener cantidad requerida del puesto
                SELECT cantidad_requerida
                INTO v_cantidad_requerida
                FROM proyectos_configuracion_personal
                WHERE id = NEW.configuracion_puesto_id;

                -- Contar asignaciones activas actuales para este puesto
                SELECT COUNT(*)
                INTO v_cantidad_asignada
                FROM operaciones_personal_asignado
                WHERE configuracion_puesto_id = NEW.configuracion_puesto_id
                  AND estado_asignacion = 'activa'
                  AND id != COALESCE(NEW.id, 0)
                  AND (
                    -- Verificar solapamiento de fechas
                    (fecha_fin IS NULL AND (NEW.fecha_fin IS NULL OR NEW.fecha_inicio <= CURRENT_DATE))
                    OR
                    (fecha_fin IS NOT NULL AND (
                        (NEW.fecha_fin IS NULL AND fecha_fin >= NEW.fecha_inicio)
                        OR
                        (NEW.fecha_fin IS NOT NULL AND
                         NEW.fecha_inicio <= fecha_fin AND
                         NEW.fecha_fin >= fecha_inicio)
                    ))
                  );

                IF v_cantidad_asignada >= v_cantidad_requerida THEN
                    RAISE EXCEPTION 'El puesto ya tiene la cantidad máxima de personal asignado (% de %)',
                        v_cantidad_asignada, v_cantidad_requerida
                    USING ERRCODE = 'P0008';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_validar_capacidad_puesto
            BEFORE INSERT OR UPDATE ON operaciones_personal_asignado
            FOR EACH ROW
            EXECUTE FUNCTION validar_capacidad_puesto();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_capacidad_puesto ON operaciones_personal_asignado;');
        DB::unprepared('DROP FUNCTION IF EXISTS validar_capacidad_puesto();');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_requisitos_puesto ON operaciones_personal_asignado;');
        DB::unprepared('DROP FUNCTION IF EXISTS validar_requisitos_puesto();');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_asignacion_sin_conflicto ON operaciones_personal_asignado;');
        DB::unprepared('DROP FUNCTION IF EXISTS validar_asignacion_sin_conflicto();');
    }
};
