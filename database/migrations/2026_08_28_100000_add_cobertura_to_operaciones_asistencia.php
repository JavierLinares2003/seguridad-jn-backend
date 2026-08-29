<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->boolean('es_cobertura')->default(false)->after('es_extra');
            $table->foreignId('asistencia_titular_id')
                ->nullable()
                ->after('es_cobertura')
                ->constrained('operaciones_asistencia')
                ->nullOnDelete();
            $table->foreignId('proyecto_cobertura_id')
                ->nullable()
                ->after('asistencia_titular_id')
                ->constrained('proyectos')
                ->nullOnDelete();
        });

        DB::statement('CREATE INDEX operaciones_asistencia_es_cobertura_idx ON operaciones_asistencia (es_cobertura, fecha_asistencia) WHERE es_cobertura = TRUE');

        // Cubridor con puesto: solo bloquea si ESE día NO está de descanso.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION validar_reemplazo_asistencia()
            RETURNS TRIGGER AS $$
            DECLARE
                v_ocupado BOOLEAN;
                v_proyecto_nombre VARCHAR(255);
            BEGIN
                IF NEW.personal_reemplazo_id IS NULL THEN
                    RETURN NEW;
                END IF;

                SELECT EXISTS (
                    SELECT 1
                    FROM operaciones_personal_asignado opa
                    WHERE opa.personal_id = NEW.personal_reemplazo_id
                      AND opa.estado_asignacion = 'activa'
                      AND opa.fecha_inicio <= NEW.fecha_asistencia
                      AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= NEW.fecha_asistencia)
                      AND NOT EXISTS (
                          SELECT 1
                          FROM operaciones_asistencia oa
                          WHERE oa.personal_asignado_id = opa.id
                            AND oa.fecha_asistencia = NEW.fecha_asistencia
                            AND oa.es_descanso = TRUE
                      )
                ) INTO v_ocupado;

                IF v_ocupado THEN
                    SELECT COALESCE(p.nombre_proyecto, 'Sin Proyecto / General')
                    INTO v_proyecto_nombre
                    FROM operaciones_personal_asignado opa
                    LEFT JOIN proyectos p ON p.id = opa.proyecto_id
                    WHERE opa.personal_id = NEW.personal_reemplazo_id
                      AND opa.estado_asignacion = 'activa'
                      AND opa.fecha_inicio <= NEW.fecha_asistencia
                      AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= NEW.fecha_asistencia)
                    LIMIT 1;

                    RAISE EXCEPTION 'El cubridor está de turno en: %. Solo puede cubrir si está de descanso o sin puesto.', v_proyecto_nombre
                    USING ERRCODE = 'P0010';
                END IF;

                NEW.fue_reemplazado := TRUE;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Asistencia directa: activo o extrero (cubridores y tab sin asignación).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION validar_asistencia()
            RETURNS TRIGGER AS $$
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
                    SELECT estado IN ('activo', 'extrero')
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

                IF NEW.hora_salida IS NOT NULL AND NEW.hora_entrada IS NULL THEN
                    RAISE EXCEPTION 'No puede registrar hora de salida sin hora de entrada'
                    USING ERRCODE = 'P0014';
                END IF;

                IF NEW.fue_reemplazado = TRUE AND NEW.personal_reemplazo_id IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el personal de reemplazo'
                    USING ERRCODE = 'P0015';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS operaciones_asistencia_es_cobertura_idx');

        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proyecto_cobertura_id');
            $table->dropConstrainedForeignId('asistencia_titular_id');
            $table->dropColumn('es_cobertura');
        });
    }
};
