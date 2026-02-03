<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operaciones_asistencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_asignado_id')
                  ->constrained('operaciones_personal_asignado')
                  ->onDelete('cascade');
            $table->date('fecha_asistencia');
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            $table->boolean('llego_tarde')->default(false);
            $table->integer('minutos_retraso')->default(0);
            $table->boolean('es_descanso')->default(false);
            $table->boolean('fue_reemplazado')->default(false);
            $table->foreignId('personal_reemplazo_id')
                  ->nullable()
                  ->constrained('personal')
                  ->nullOnDelete();
            $table->text('motivo_reemplazo')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            // Índices
            $table->index('personal_asignado_id');
            $table->index('fecha_asistencia');
            $table->index('personal_reemplazo_id');

            // Unique constraint: un registro por asignación por día
            $table->unique(['personal_asignado_id', 'fecha_asistencia'], 'asistencia_unica_dia');
        });

        // =====================================================
        // TRIGGER: Calcular si llegó tarde basado en hora del turno
        // =====================================================
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

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_calcular_retraso_asistencia
            BEFORE INSERT OR UPDATE ON operaciones_asistencia
            FOR EACH ROW
            EXECUTE FUNCTION calcular_retraso_asistencia();
        ");

        // =====================================================
        // TRIGGER: Validar que el reemplazo esté disponible
        // =====================================================
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

        DB::unprepared("
            CREATE TRIGGER trg_validar_reemplazo_asistencia
            BEFORE INSERT OR UPDATE ON operaciones_asistencia
            FOR EACH ROW
            EXECUTE FUNCTION validar_reemplazo_asistencia();
        ");

        // =====================================================
        // FUNCIÓN: Generar descansos automáticos para turnos 24h
        // Esta función se llama manualmente o por cron job
        // =====================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION generar_descansos_automaticos(
                p_fecha_inicio DATE,
                p_fecha_fin DATE
            )
            RETURNS TABLE (
                asignacion_id BIGINT,
                fecha_descanso DATE,
                creado BOOLEAN
            ) AS \$\$
            DECLARE
                v_asignacion RECORD;
                v_fecha_actual DATE;
                v_ultimo_trabajo DATE;
                v_dias_trabajados INTEGER;
                v_patron_trabajo INTEGER;
                v_patron_descanso INTEGER;
            BEGIN
                -- Recorrer asignaciones activas con turnos que requieren descanso
                FOR v_asignacion IN
                    SELECT
                        opa.id as asignacion_id,
                        opa.fecha_inicio,
                        COALESCE(opa.fecha_fin, p_fecha_fin) as fecha_fin,
                        t.requiere_descanso,
                        t.horas_trabajo
                    FROM operaciones_personal_asignado opa
                    INNER JOIN turnos t ON t.id = opa.turno_id
                    WHERE opa.estado_asignacion = 'activa'
                      AND t.requiere_descanso = TRUE
                      AND opa.fecha_inicio <= p_fecha_fin
                      AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= p_fecha_inicio)
                LOOP
                    -- Determinar patrón según horas de trabajo
                    -- Turno 24h: trabaja 1 día, descansa 1 día (patrón 1-1)
                    -- Turno 12h: trabaja 2 días, descansa 2 días (patrón 2-2)
                    IF v_asignacion.horas_trabajo >= 24 THEN
                        v_patron_trabajo := 1;
                        v_patron_descanso := 1;
                    ELSIF v_asignacion.horas_trabajo >= 12 THEN
                        v_patron_trabajo := 2;
                        v_patron_descanso := 2;
                    ELSE
                        -- Turnos normales no generan descanso automático
                        CONTINUE;
                    END IF;

                    v_fecha_actual := GREATEST(v_asignacion.fecha_inicio, p_fecha_inicio);
                    v_dias_trabajados := 0;

                    WHILE v_fecha_actual <= LEAST(v_asignacion.fecha_fin, p_fecha_fin) LOOP
                        -- Calcular día en el ciclo
                        v_dias_trabajados := ((v_fecha_actual - v_asignacion.fecha_inicio) %
                                             (v_patron_trabajo + v_patron_descanso));

                        -- Determinar si es día de descanso
                        IF v_dias_trabajados >= v_patron_trabajo THEN
                            -- Es día de descanso
                            -- Insertar si no existe
                            INSERT INTO operaciones_asistencia (
                                personal_asignado_id,
                                fecha_asistencia,
                                es_descanso,
                                observaciones,
                                created_at,
                                updated_at
                            )
                            VALUES (
                                v_asignacion.asignacion_id,
                                v_fecha_actual,
                                TRUE,
                                'Descanso generado automáticamente',
                                NOW(),
                                NOW()
                            )
                            ON CONFLICT (personal_asignado_id, fecha_asistencia) DO NOTHING;

                            -- Retornar información
                            asignacion_id := v_asignacion.asignacion_id;
                            fecha_descanso := v_fecha_actual;
                            creado := TRUE;
                            RETURN NEXT;
                        END IF;

                        v_fecha_actual := v_fecha_actual + INTERVAL '1 day';
                    END LOOP;
                END LOOP;

                RETURN;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // =====================================================
        // TRIGGER: Validar coherencia de datos de asistencia
        // =====================================================
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

                -- Verificar que la fecha esté dentro del rango de la asignación
                IF NEW.fecha_asistencia < v_fecha_inicio THEN
                    RAISE EXCEPTION 'La fecha de asistencia es anterior al inicio de la asignación'
                    USING ERRCODE = 'P0012';
                END IF;

                IF v_fecha_fin IS NOT NULL AND NEW.fecha_asistencia > v_fecha_fin THEN
                    RAISE EXCEPTION 'La fecha de asistencia es posterior al fin de la asignación'
                    USING ERRCODE = 'P0013';
                END IF;

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

        DB::unprepared("
            CREATE TRIGGER trg_validar_asistencia
            BEFORE INSERT OR UPDATE ON operaciones_asistencia
            FOR EACH ROW
            EXECUTE FUNCTION validar_asistencia();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_asistencia ON operaciones_asistencia;');
        DB::unprepared('DROP FUNCTION IF EXISTS validar_asistencia();');

        DB::unprepared('DROP FUNCTION IF EXISTS generar_descansos_automaticos(DATE, DATE);');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_reemplazo_asistencia ON operaciones_asistencia;');
        DB::unprepared('DROP FUNCTION IF EXISTS validar_reemplazo_asistencia();');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_calcular_retraso_asistencia ON operaciones_asistencia;');
        DB::unprepared('DROP FUNCTION IF EXISTS calcular_retraso_asistencia();');

        Schema::dropIfExists('operaciones_asistencia');
    }
};
