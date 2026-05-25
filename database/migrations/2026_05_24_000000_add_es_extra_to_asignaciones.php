<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones_personal_asignado', function (Blueprint $table) {
            $table->boolean('es_extra')->default(false)->after('requisitos_forzados');
        });

        // Actualizar trigger: permitir estado 'extrero' cuando es_extra = true
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_requisitos_puesto()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_personal             RECORD;
                v_config               RECORD;
                v_errores              TEXT[];
                v_sexo_nombre          VARCHAR(20);
                v_sexo_personal_nombre VARCHAR(20);
            BEGIN
                -- No re-validar en UPDATE (finalizar, suspender, reactivar, etc.)
                IF TG_OP = 'UPDATE' THEN
                    RETURN NEW;
                END IF;

                -- Si se forzaron los requisitos desde la aplicación, saltar validaciones
                IF NEW.requisitos_forzados = true THEN
                    RETURN NEW;
                END IF;

                -- Sin configuración de puesto → asignación general sin restricciones
                IF NEW.configuracion_puesto_id IS NULL THEN
                    RETURN NEW;
                END IF;

                -- Obtener datos del personal
                SELECT p.*,
                       EXTRACT(YEAR FROM AGE(CURRENT_DATE, p.fecha_nacimiento))::INTEGER AS edad
                INTO v_personal
                FROM personal p
                WHERE p.id = NEW.personal_id AND p.deleted_at IS NULL;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'Personal no encontrado o está eliminado'
                    USING ERRCODE = 'P0002';
                END IF;

                -- Para extraeros: solo se permite si es_extra = true
                IF NEW.es_extra = true THEN
                    IF v_personal.estado != 'extrero' THEN
                        RAISE EXCEPTION 'Las asignaciones extra solo aplican a personal con estado extrero. Estado actual: %', v_personal.estado
                        USING ERRCODE = 'P0003';
                    END IF;
                ELSE
                    IF v_personal.estado != 'activo' THEN
                        RAISE EXCEPTION 'El personal no está activo. Estado actual: %', v_personal.estado
                        USING ERRCODE = 'P0003';
                    END IF;
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

                IF NEW.proyecto_id IS NOT NULL AND v_config.proyecto_id != NEW.proyecto_id THEN
                    RAISE EXCEPTION 'La configuración de puesto no pertenece al proyecto especificado'
                    USING ERRCODE = 'P0005';
                END IF;

                IF v_config.estado != 'activo' THEN
                    RAISE EXCEPTION 'La configuración de puesto no está activa'
                    USING ERRCODE = 'P0006';
                END IF;

                v_errores := ARRAY[]::TEXT[];

                -- Validar edad
                IF v_config.edad_minima IS NOT NULL AND v_personal.edad < v_config.edad_minima THEN
                    v_errores := array_append(v_errores,
                        format('Edad (%s años) menor al mínimo requerido (%s años)',
                               v_personal.edad, v_config.edad_minima));
                END IF;

                IF v_config.edad_maxima IS NOT NULL AND v_personal.edad > v_config.edad_maxima THEN
                    v_errores := array_append(v_errores,
                        format('Edad (%s años) mayor al máximo permitido (%s años)',
                               v_personal.edad, v_config.edad_maxima));
                END IF;

                -- Validar sexo (si está especificado)
                IF v_config.sexo_id IS NOT NULL THEN
                    SELECT nombre INTO v_sexo_nombre FROM sexos WHERE id = v_config.sexo_id;
                    SELECT nombre INTO v_sexo_personal_nombre FROM sexos WHERE id = v_personal.sexo_id;

                    IF v_sexo_nombre = 'Ambos' THEN
                        IF v_sexo_personal_nombre NOT IN ('Masculino', 'Femenino') THEN
                            v_errores := array_append(v_errores, 'El puesto requiere personal Masculino o Femenino');
                        END IF;
                    ELSE
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

                IF array_length(v_errores, 1) > 0 THEN
                    RAISE EXCEPTION 'El personal no cumple los requisitos del puesto: %',
                        array_to_string(v_errores, '; ')
                    USING ERRCODE = 'P0007';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Actualizar trigger: saltear validación de capacidad máxima para extras
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_capacidad_puesto()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_cantidad_requerida INTEGER;
                v_cantidad_asignada  INTEGER;
            BEGIN
                -- Solo validar para asignaciones activas
                IF NEW.estado_asignacion != 'activa' THEN
                    RETURN NEW;
                END IF;

                -- Los extras van más allá de la capacidad configurada; no se valida
                IF NEW.es_extra = true THEN
                    RETURN NEW;
                END IF;

                -- Obtener cantidad requerida del puesto
                SELECT cantidad_requerida
                INTO v_cantidad_requerida
                FROM proyectos_configuracion_personal
                WHERE id = NEW.configuracion_puesto_id;

                -- Contar asignaciones activas actuales para este puesto (excluyendo extras)
                SELECT COUNT(*)
                INTO v_cantidad_asignada
                FROM operaciones_personal_asignado
                WHERE configuracion_puesto_id = NEW.configuracion_puesto_id
                  AND estado_asignacion = 'activa'
                  AND es_extra = false
                  AND id != COALESCE(NEW.id, 0)
                  AND (
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

    public function down(): void
    {
        Schema::table('operaciones_personal_asignado', function (Blueprint $table) {
            $table->dropColumn('es_extra');
        });
    }
};
