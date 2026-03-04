<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Permite registrar asistencia a personal sin asignación activa.
     * - Si tiene asignación: usa personal_asignado_id (comportamiento actual)
     * - Si no tiene asignación: usa personal_id directamente
     */
    public function up(): void
    {
        // 1. Agregar campo personal_id y hacer personal_asignado_id nullable
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            // Agregar personal_id para asistencia directa
            $table->foreignId('personal_id')
                  ->nullable()
                  ->after('personal_asignado_id')
                  ->constrained('personal')
                  ->nullOnDelete();

            // Agregar índice
            $table->index('personal_id');
        });

        // 2. Eliminar el constraint NOT NULL de personal_asignado_id
        DB::statement('ALTER TABLE operaciones_asistencia ALTER COLUMN personal_asignado_id DROP NOT NULL');

        // 3. Eliminar el unique constraint actual y crear uno nuevo
        DB::statement('ALTER TABLE operaciones_asistencia DROP CONSTRAINT IF EXISTS asistencia_unica_dia');

        // Crear nuevo constraint que permita unique por (personal_asignado_id, fecha) O (personal_id, fecha)
        // Usamos un índice parcial único para cada caso
        DB::statement('
            CREATE UNIQUE INDEX asistencia_unica_dia_asignado
            ON operaciones_asistencia (personal_asignado_id, fecha_asistencia)
            WHERE personal_asignado_id IS NOT NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX asistencia_unica_dia_personal
            ON operaciones_asistencia (personal_id, fecha_asistencia)
            WHERE personal_id IS NOT NULL AND personal_asignado_id IS NULL
        ');

        // 4. Agregar constraint CHECK para asegurar que tenga al menos uno de los dos
        DB::statement('
            ALTER TABLE operaciones_asistencia
            ADD CONSTRAINT chk_personal_o_asignacion
            CHECK (personal_asignado_id IS NOT NULL OR personal_id IS NOT NULL)
        ');

        // 5. Modificar trigger de validación para manejar asistencia sin asignación
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

                -- Validaciones comunes

                -- Si es descanso, limpiar campos de asistencia
                IF NEW.es_descanso = TRUE THEN
                    NEW.hora_entrada := NULL;
                    NEW.hora_salida := NULL;
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
                END IF;

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

                IF NEW.fue_reemplazado = TRUE AND NEW.motivo_reemplazo IS NULL THEN
                    RAISE EXCEPTION 'Debe especificar el motivo del reemplazo'
                    USING ERRCODE = 'P0016';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // 6. Modificar trigger de cálculo de retraso para manejar asistencia sin asignación
        DB::unprepared("
            CREATE OR REPLACE FUNCTION calcular_retraso_asistencia()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_hora_inicio_turno TIME;
                v_tolerancia_minutos INTEGER := 5; -- 5 minutos de tolerancia
            BEGIN
                -- Solo calcular si hay hora de entrada y no es descanso
                IF NEW.hora_entrada IS NULL OR NEW.es_descanso = TRUE THEN
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
                    RETURN NEW;
                END IF;

                -- Solo calcular retraso si hay asignación con turno
                IF NEW.personal_asignado_id IS NOT NULL THEN
                    -- Obtener hora de inicio del turno
                    SELECT t.hora_inicio
                    INTO v_hora_inicio_turno
                    FROM operaciones_personal_asignado opa
                    INNER JOIN turnos t ON t.id = opa.turno_id
                    WHERE opa.id = NEW.personal_asignado_id;

                    IF v_hora_inicio_turno IS NOT NULL THEN
                        -- Calcular diferencia en minutos
                        NEW.minutos_retraso := GREATEST(0,
                            EXTRACT(EPOCH FROM (NEW.hora_entrada - v_hora_inicio_turno)) / 60
                        )::INTEGER;

                        -- Considerar tarde si excede tolerancia
                        NEW.llego_tarde := NEW.minutos_retraso > v_tolerancia_minutos;

                        -- Si está dentro de tolerancia, no contar retraso
                        IF NEW.minutos_retraso <= v_tolerancia_minutos THEN
                            NEW.minutos_retraso := 0;
                        END IF;
                    END IF;
                ELSE
                    -- Sin asignación = sin turno, no hay retraso que calcular
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
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
        // Restaurar trigger original de validación
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validar_asistencia()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_asignacion_activa BOOLEAN;
                v_fecha_inicio DATE;
                v_fecha_fin DATE;
            BEGIN
                -- Verificar que la asignación esté activa
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

        // Restaurar trigger original de retraso
        DB::unprepared("
            CREATE OR REPLACE FUNCTION calcular_retraso_asistencia()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_hora_inicio_turno TIME;
                v_tolerancia_minutos INTEGER := 5;
            BEGIN
                IF NEW.hora_entrada IS NULL OR NEW.es_descanso = TRUE THEN
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
                    RETURN NEW;
                END IF;

                SELECT t.hora_inicio
                INTO v_hora_inicio_turno
                FROM operaciones_personal_asignado opa
                INNER JOIN turnos t ON t.id = opa.turno_id
                WHERE opa.id = NEW.personal_asignado_id;

                IF v_hora_inicio_turno IS NOT NULL THEN
                    NEW.minutos_retraso := GREATEST(0,
                        EXTRACT(EPOCH FROM (NEW.hora_entrada - v_hora_inicio_turno)) / 60
                    )::INTEGER;
                    NEW.llego_tarde := NEW.minutos_retraso > v_tolerancia_minutos;
                    IF NEW.minutos_retraso <= v_tolerancia_minutos THEN
                        NEW.minutos_retraso := 0;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Eliminar constraints e índices nuevos
        DB::statement('ALTER TABLE operaciones_asistencia DROP CONSTRAINT IF EXISTS chk_personal_o_asignacion');
        DB::statement('DROP INDEX IF EXISTS asistencia_unica_dia_personal');
        DB::statement('DROP INDEX IF EXISTS asistencia_unica_dia_asignado');

        // Restaurar constraint original
        DB::statement('
            ALTER TABLE operaciones_asistencia
            ADD CONSTRAINT asistencia_unica_dia
            UNIQUE (personal_asignado_id, fecha_asistencia)
        ');

        // Restaurar NOT NULL en personal_asignado_id
        DB::statement('ALTER TABLE operaciones_asistencia ALTER COLUMN personal_asignado_id SET NOT NULL');

        // Eliminar columna personal_id
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->dropForeign(['personal_id']);
            $table->dropIndex(['personal_id']);
            $table->dropColumn('personal_id');
        });
    }
};
