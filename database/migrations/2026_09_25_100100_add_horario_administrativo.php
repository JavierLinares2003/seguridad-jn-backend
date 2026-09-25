<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->time('horario_entrada')->nullable();
            $table->time('horario_salida')->nullable();
        });

        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutos_salida_temprana')->default(0);
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
            BEGIN
                IF NEW.hora_entrada IS NULL OR NEW.es_descanso = TRUE OR NEW.es_ausente = TRUE THEN
                    NEW.llego_tarde := FALSE;
                    NEW.minutos_retraso := 0;
                    NEW.minutos_salida_temprana := 0;
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
                    END IF;

                    IF v_hora_fin_turno IS NOT NULL AND NEW.hora_salida IS NOT NULL THEN
                        NEW.minutos_salida_temprana := GREATEST(0,
                            EXTRACT(EPOCH FROM (v_hora_fin_turno - NEW.hora_salida::time)) / 60
                        )::INTEGER;
                        IF NEW.minutos_salida_temprana <= v_tolerancia_minutos THEN
                            NEW.minutos_salida_temprana := 0;
                        END IF;
                    ELSE
                        NEW.minutos_salida_temprana := 0;
                    END IF;
                ELSE
                    SELECT horario_entrada, horario_salida
                    INTO v_horario_entrada, v_horario_salida
                    FROM personal
                    WHERE id = NEW.personal_id;

                    IF v_horario_entrada IS NOT NULL AND NEW.hora_entrada IS NOT NULL THEN
                        NEW.minutos_retraso := GREATEST(0,
                            EXTRACT(EPOCH FROM (NEW.hora_entrada::time - v_horario_entrada)) / 60
                        )::INTEGER;
                        NEW.llego_tarde := NEW.minutos_retraso > v_tolerancia_minutos;
                        IF NEW.minutos_retraso <= v_tolerancia_minutos THEN
                            NEW.minutos_retraso := 0;
                        END IF;
                    ELSE
                        NEW.llego_tarde := FALSE;
                        NEW.minutos_retraso := 0;
                    END IF;

                    IF v_horario_salida IS NOT NULL AND NEW.hora_salida IS NOT NULL THEN
                        NEW.minutos_salida_temprana := GREATEST(0,
                            EXTRACT(EPOCH FROM (v_horario_salida - NEW.hora_salida::time)) / 60
                        )::INTEGER;
                        IF NEW.minutos_salida_temprana <= v_tolerancia_minutos THEN
                            NEW.minutos_salida_temprana := 0;
                        END IF;
                    ELSE
                        NEW.minutos_salida_temprana := 0;
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
            $table->dropColumn(['horario_entrada', 'horario_salida']);
        });

        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->dropColumn('minutos_salida_temprana');
        });
    }
};
