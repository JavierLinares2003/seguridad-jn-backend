<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Actualiza el trigger de validación de requisitos para soportar
     * la opción "Ambos" que acepta Masculino o Femenino.
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_requisitos_puesto()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_personal RECORD;
                v_config RECORD;
                v_edad INTEGER;
                v_errores TEXT[];
                v_sexo_nombre VARCHAR(20);
                v_sexo_personal_nombre VARCHAR(20);
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
                IF v_config.sexo_id IS NOT NULL THEN
                    -- Obtener nombre del sexo requerido
                    SELECT nombre INTO v_sexo_nombre FROM sexos WHERE id = v_config.sexo_id;
                    -- Obtener nombre del sexo del personal
                    SELECT nombre INTO v_sexo_personal_nombre FROM sexos WHERE id = v_personal.sexo_id;

                    IF v_sexo_nombre = 'Ambos' THEN
                        -- 'Ambos' acepta solo Masculino o Femenino
                        IF v_sexo_personal_nombre NOT IN ('Masculino', 'Femenino') THEN
                            v_errores := array_append(v_errores, 'El puesto requiere personal Masculino o Femenino');
                        END IF;
                    ELSE
                        -- Validación normal: debe coincidir exactamente
                        IF v_personal.sexo_id != v_config.sexo_id THEN
                            v_errores := array_append(v_errores, 'El sexo no coincide con el requerido para el puesto');
                        END IF;
                    END IF;
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al trigger anterior sin soporte para "Ambos"
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
    }
};
