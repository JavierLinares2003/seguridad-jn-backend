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
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_reemplazo_asistencia()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_reemplazo_ocupado BOOLEAN;
                v_proyecto_nombre VARCHAR(255);
            BEGIN
                -- Solo validar si hay reemplazo
                IF NEW.personal_reemplazo_id IS NULL THEN
                    RETURN NEW;
                END IF;

                -- Verificar que el reemplazo no tenga asignación activa ese día
                SELECT EXISTS (
                    SELECT 1
                    FROM operaciones_personal_asignado opa
                    LEFT JOIN proyectos p ON p.id = opa.proyecto_id
                    WHERE opa.personal_id = NEW.personal_reemplazo_id
                      AND opa.estado_asignacion = 'activa'
                      AND opa.fecha_inicio <= NEW.fecha_asistencia
                      AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= NEW.fecha_asistencia)
                ), (
                    SELECT COALESCE(p.nombre_proyecto, 'Sin Proyecto / General')
                    FROM operaciones_personal_asignado opa
                    LEFT JOIN proyectos p ON p.id = opa.proyecto_id
                    WHERE opa.personal_id = NEW.personal_reemplazo_id
                      AND opa.estado_asignacion = 'activa'
                      AND opa.fecha_inicio <= NEW.fecha_asistencia
                      AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= NEW.fecha_asistencia)
                    LIMIT 1
                )
                INTO v_reemplazo_ocupado, v_proyecto_nombre;

                IF v_reemplazo_ocupado THEN
                    RAISE EXCEPTION 'El personal de reemplazo ya tiene asignación activa en: %', v_proyecto_nombre
                    USING ERRCODE = 'P0010';
                END IF;

                -- Marcar como reemplazado
                NEW.fue_reemplazado := TRUE;

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
        // Revert to original version (INNER JOIN)
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_reemplazo_asistencia()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_reemplazo_ocupado BOOLEAN;
                v_proyecto_nombre VARCHAR(255);
            BEGIN
                -- Solo validar si hay reemplazo
                IF NEW.personal_reemplazo_id IS NULL THEN
                    RETURN NEW;
                END IF;

                -- Verificar que el reemplazo no tenga asignación activa ese día
                SELECT EXISTS (
                    SELECT 1
                    FROM operaciones_personal_asignado opa
                    INNER JOIN proyectos p ON p.id = opa.proyecto_id
                    WHERE opa.personal_id = NEW.personal_reemplazo_id
                      AND opa.estado_asignacion = 'activa'
                      AND opa.fecha_inicio <= NEW.fecha_asistencia
                      AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= NEW.fecha_asistencia)
                ), (
                    SELECT p.nombre_proyecto
                    FROM operaciones_personal_asignado opa
                    INNER JOIN proyectos p ON p.id = opa.proyecto_id
                    WHERE opa.personal_id = NEW.personal_reemplazo_id
                      AND opa.estado_asignacion = 'activa'
                      AND opa.fecha_inicio <= NEW.fecha_asistencia
                      AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= NEW.fecha_asistencia)
                    LIMIT 1
                )
                INTO v_reemplazo_ocupado, v_proyecto_nombre;

                IF v_reemplazo_ocupado THEN
                    RAISE EXCEPTION 'El personal de reemplazo ya tiene asignación activa en: %', v_proyecto_nombre
                    USING ERRCODE = 'P0010';
                END IF;

                -- Marcar como reemplazado
                NEW.fue_reemplazado := TRUE;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }
};
