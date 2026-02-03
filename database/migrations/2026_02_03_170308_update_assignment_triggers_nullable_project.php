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
        // 1. Update validar_requisitos_puesto
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_requisitos_puesto()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_personal RECORD;
                v_config RECORD;
                v_edad INTEGER;
                v_errores TEXT[];
            BEGIN
                -- Si no hay configuración de puesto (asignación general), saltar validaciones
                IF NEW.configuracion_puesto_id IS NULL THEN
                    RETURN NEW;
                END IF;

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
                -- Solo si hay proyecto (aunque si hay config, debería haber proyecto)
                IF NEW.proyecto_id IS NOT NULL AND v_config.proyecto_id != NEW.proyecto_id THEN
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

        // 2. Update validar_capacidad_puesto
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

                -- Si no hay configuración de puesto, saltar validación de capacidad
                IF NEW.configuracion_puesto_id IS NULL THEN
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original versions (without NULL checks)
        // ... (Skipping full revert logic for brevity as it's just removing the IF check)
        // Ideally we should put back the original code.
        
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
    }
};
