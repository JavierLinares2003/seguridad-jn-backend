<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trigger_generar_correlativo_proyecto ON proyectos");
        DB::unprepared("DROP FUNCTION IF EXISTS generar_correlativo_proyecto()");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION generar_correlativo_proyecto()
            RETURNS TRIGGER AS $$
            DECLARE
                prefijo VARCHAR;
                anio VARCHAR;
                secuencia INTEGER;
                nuevo_correlativo VARCHAR;
                secuencia_texto VARCHAR;
            BEGIN
                SELECT COALESCE(prefijo_correlativo, 'PROY') INTO prefijo
                FROM tipos_proyecto
                WHERE id = NEW.tipo_proyecto_id;

                anio := TO_CHAR(CURRENT_DATE, 'YYYY');

                -- Evita colisiones concurrentes por tipo+año
                PERFORM pg_advisory_xact_lock(
                    hashtext('proyecto_correlativo_' || COALESCE(prefijo, 'PROY') || '_' || anio)
                );

                -- Incluye soft-deleted: el unique de correlativo también los cuenta
                SELECT COALESCE(MAX(
                    NULLIF(regexp_replace(SPLIT_PART(correlativo, '-', 3), '[^0-9]', '', 'g'), '')::INTEGER
                ), 0) + 1
                INTO secuencia
                FROM proyectos
                WHERE correlativo LIKE (prefijo || '-' || anio || '-%');

                LOOP
                    IF secuencia < 1000 THEN
                        secuencia_texto := LPAD(secuencia::TEXT, 3, '0');
                    ELSE
                        secuencia_texto := secuencia::TEXT;
                    END IF;

                    nuevo_correlativo := prefijo || '-' || anio || '-' || secuencia_texto;

                    EXIT WHEN NOT EXISTS (
                        SELECT 1 FROM proyectos WHERE correlativo = nuevo_correlativo
                    );

                    secuencia := secuencia + 1;
                END LOOP;

                NEW.correlativo := nuevo_correlativo;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trigger_generar_correlativo_proyecto
            BEFORE INSERT ON proyectos
            FOR EACH ROW
            EXECUTE FUNCTION generar_correlativo_proyecto();
        ");
    }

    public function down(): void
    {
        // Restaura la versión anterior (sin loop de unicidad)
        DB::unprepared("DROP TRIGGER IF EXISTS trigger_generar_correlativo_proyecto ON proyectos");
        DB::unprepared("DROP FUNCTION IF EXISTS generar_correlativo_proyecto()");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION generar_correlativo_proyecto()
            RETURNS TRIGGER AS $$
            DECLARE
                prefijo VARCHAR;
                anio VARCHAR;
                secuencia INTEGER;
                nuevo_correlativo VARCHAR;
            BEGIN
                SELECT COALESCE(prefijo_correlativo, 'PROY') INTO prefijo
                FROM tipos_proyecto
                WHERE id = NEW.tipo_proyecto_id;

                anio := TO_CHAR(CURRENT_DATE, 'YYYY');

                SELECT COALESCE(MAX(CAST(SPLIT_PART(correlativo, '-', 3) AS INTEGER)), 0) + 1
                INTO secuencia
                FROM proyectos
                WHERE tipo_proyecto_id = NEW.tipo_proyecto_id
                AND correlativo LIKE (prefijo || '-' || anio || '-%');

                nuevo_correlativo := prefijo || '-' || anio || '-' || LPAD(secuencia::TEXT, 3, '0');
                NEW.correlativo := nuevo_correlativo;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trigger_generar_correlativo_proyecto
            BEFORE INSERT ON proyectos
            FOR EACH ROW
            EXECUTE FUNCTION generar_correlativo_proyecto();
        ");
    }
};
