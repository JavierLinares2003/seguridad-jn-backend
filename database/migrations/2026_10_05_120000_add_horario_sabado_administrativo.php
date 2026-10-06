<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sábados de personal administrativo: horario propio (por defecto 07:00–11:00).
 * El de lunes a viernes no se toca. El trigger de minutos usa el horario del día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            if (!Schema::hasColumn('personal', 'horario_sabado_entrada')) {
                $table->time('horario_sabado_entrada')->nullable();
            }
            if (!Schema::hasColumn('personal', 'horario_sabado_salida')) {
                $table->time('horario_sabado_salida')->nullable();
            }
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION calcular_retraso_asistencia()
            RETURNS TRIGGER AS $$
            DECLARE
                v_hora_inicio_turno TIME;
                v_hora_fin_turno TIME;
                v_tolerancia_minutos INTEGER := 5;
                v_horario_entrada TIME;
                v_horario_salida TIME;
                v_dow INTEGER;
            BEGIN
                IF NEW.hora_entrada IS NULL OR NEW.es_descanso = TRUE OR NEW.es_ausente = TRUE THEN
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
                    NEW.minutos_salida_temprana := 0;
                    NEW.minutos_entrada_anticipada := 0;
                    NEW.minutos_salida_tarde := 0;
                    RETURN NEW;
                END IF;

                IF NEW.personal_asignado_id IS NOT NULL THEN
                    SELECT t.hora_inicio, t.hora_fin
                    INTO v_hora_inicio_turno, v_hora_fin_turno
                    FROM operaciones_personal_asignado opa
                    INNER JOIN turnos t ON t.id = opa.turno_id
                    WHERE opa.id = NEW.personal_asignado_id;

                    IF v_hora_inicio_turno IS NOT NULL THEN
                        NEW.minutos_retraso := GREATEST(0,
                            EXTRACT(EPOCH FROM (NEW.hora_entrada::time - v_hora_inicio_turno)) / 60
                        )::INTEGER;
                        NEW.llego_tarde := NEW.minutos_retraso > v_tolerancia_minutos;
                        IF NEW.minutos_retraso <= v_tolerancia_minutos THEN
                            NEW.minutos_retraso := 0;
                        END IF;

                        NEW.minutos_entrada_anticipada := GREATEST(0,
                            EXTRACT(EPOCH FROM (v_hora_inicio_turno - NEW.hora_entrada::time)) / 60
                        )::INTEGER;
                        IF NEW.minutos_entrada_anticipada <= v_tolerancia_minutos THEN
                            NEW.minutos_entrada_anticipada := 0;
                        END IF;
                    ELSE
                        NEW.minutos_retraso := 0;
                        NEW.llego_tarde := FALSE;
                        NEW.minutos_entrada_anticipada := 0;
                    END IF;

                    IF v_hora_fin_turno IS NOT NULL AND NEW.hora_salida IS NOT NULL THEN
                        NEW.minutos_salida_temprana := GREATEST(0,
                            EXTRACT(EPOCH FROM (v_hora_fin_turno - NEW.hora_salida::time)) / 60
                        )::INTEGER;
                        IF NEW.minutos_salida_temprana <= v_tolerancia_minutos THEN
                            NEW.minutos_salida_temprana := 0;
                        END IF;

                        NEW.minutos_salida_tarde := GREATEST(0,
                            EXTRACT(EPOCH FROM (NEW.hora_salida::time - v_hora_fin_turno)) / 60
                        )::INTEGER;
                        IF NEW.minutos_salida_tarde <= v_tolerancia_minutos THEN
                            NEW.minutos_salida_tarde := 0;
                        END IF;
                    ELSE
                        NEW.minutos_salida_temprana := 0;
                        NEW.minutos_salida_tarde := 0;
                    END IF;
                ELSE
                    v_dow := EXTRACT(DOW FROM NEW.fecha_asistencia)::INTEGER;
                    IF v_dow = 6 THEN
                        SELECT COALESCE(horario_sabado_entrada, TIME '07:00'),
                               COALESCE(horario_sabado_salida, TIME '11:00')
                        INTO v_horario_entrada, v_horario_salida
                        FROM personal
                        WHERE id = NEW.personal_id;
                    ELSE
                        SELECT horario_entrada, horario_salida
                        INTO v_horario_entrada, v_horario_salida
                        FROM personal
                        WHERE id = NEW.personal_id;
                    END IF;

                    IF v_horario_entrada IS NOT NULL AND NEW.hora_entrada IS NOT NULL THEN
                        NEW.minutos_retraso := GREATEST(0,
                            EXTRACT(EPOCH FROM (NEW.hora_entrada::time - v_horario_entrada)) / 60
                        )::INTEGER;
                        NEW.llego_tarde := NEW.minutos_retraso > v_tolerancia_minutos;
                        IF NEW.minutos_retraso <= v_tolerancia_minutos THEN
                            NEW.minutos_retraso := 0;
                        END IF;

                        NEW.minutos_entrada_anticipada := GREATEST(0,
                            EXTRACT(EPOCH FROM (v_horario_entrada - NEW.hora_entrada::time)) / 60
                        )::INTEGER;
                        IF NEW.minutos_entrada_anticipada <= v_tolerancia_minutos THEN
                            NEW.minutos_entrada_anticipada := 0;
                        END IF;
                    ELSE
                        NEW.llego_tarde := FALSE;
                        NEW.minutos_retraso := 0;
                        NEW.minutos_entrada_anticipada := 0;
                    END IF;

                    IF v_horario_salida IS NOT NULL AND NEW.hora_salida IS NOT NULL THEN
                        NEW.minutos_salida_temprana := GREATEST(0,
                            EXTRACT(EPOCH FROM (v_horario_salida - NEW.hora_salida::time)) / 60
                        )::INTEGER;
                        IF NEW.minutos_salida_temprana <= v_tolerancia_minutos THEN
                            NEW.minutos_salida_temprana := 0;
                        END IF;

                        NEW.minutos_salida_tarde := GREATEST(0,
                            EXTRACT(EPOCH FROM (NEW.hora_salida::time - v_horario_salida)) / 60
                        )::INTEGER;
                        IF NEW.minutos_salida_tarde <= v_tolerancia_minutos THEN
                            NEW.minutos_salida_tarde := 0;
                        END IF;
                    ELSE
                        NEW.minutos_salida_temprana := 0;
                        NEW.minutos_salida_tarde := 0;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            if (Schema::hasColumn('personal', 'horario_sabado_entrada')) {
                $table->dropColumn('horario_sabado_entrada');
            }
            if (Schema::hasColumn('personal', 'horario_sabado_salida')) {
                $table->dropColumn('horario_sabado_salida');
            }
        });
    }
};
