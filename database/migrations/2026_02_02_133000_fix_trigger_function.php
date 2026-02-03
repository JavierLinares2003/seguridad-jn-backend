<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trigger_generar_correlativo_proyecto ON proyectos");
        DB::unprepared("DROP FUNCTION IF EXISTS generar_correlativo_proyecto");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION generar_correlativo_proyecto()
            RETURNS TRIGGER AS $$
            DECLARE
                prefijo VARCHAR;
                anio VARCHAR;
                secuencia INTEGER;
                nuevo_correlativo VARCHAR;
            BEGIN
                -- Obtener el prefijo del tipo de proyecto (Default a 'PROY' si no existe)
                SELECT COALESCE(prefijo_correlativo, 'PROY') INTO prefijo
                FROM tipos_proyecto
                WHERE id = NEW.tipo_proyecto_id;
                
                -- Obtener el año actual
                anio := TO_CHAR(CURRENT_DATE, 'YYYY');
                
                -- Obtener el último correlativo para este tipo y año
                -- Formato esperado: PREFIJO-AAAA-SECUEN (ej: SEG-2024-001)
                -- Se asume formato estricto con guiones. Si falla el split/cast, ignora errores y usa 0.
                
                SELECT COALESCE(MAX(CAST(SPLIT_PART(correlativo, '-', 3) AS INTEGER)), 0) + 1
                INTO secuencia
                FROM proyectos
                WHERE tipo_proyecto_id = NEW.tipo_proyecto_id
                AND correlativo LIKE (prefijo || '-' || anio || '-%');
                
                -- Generar el nuevo correlativo
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

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trigger_generar_correlativo_proyecto ON proyectos");
        DB::unprepared("DROP FUNCTION IF EXISTS generar_correlativo_proyecto");
    }
};
