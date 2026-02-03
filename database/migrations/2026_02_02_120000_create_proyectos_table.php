<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_proyecto_id')->constrained('tipos_proyecto');
            $table->string('correlativo', 50)->unique();
            $table->string('nombre_proyecto', 255);
            $table->text('descripcion')->nullable();
            $table->string('empresa_cliente', 200);
            $table->string('estado_proyecto', 20)->default('planificacion');
            $table->date('fecha_inicio_estimada')->nullable();
            $table->date('fecha_fin_estimada')->nullable();
            $table->date('fecha_inicio_real')->nullable();
            $table->date('fecha_fin_real')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Función para generar correlativo
        DB::unprepared("
            CREATE OR REPLACE FUNCTION generar_correlativo_proyecto()
            RETURNS TRIGGER AS $$
            DECLARE
                prefijo VARCHAR;
                anio VARCHAR;
                secuencia INTEGER;
                nuevo_correlativo VARCHAR;
            BEGIN
                -- Obtener el prefijo del tipo de proyecto
                SELECT prefijo_correlativo INTO prefijo
                FROM tipos_proyecto
                WHERE id = NEW.tipo_proyecto_id;
                
                -- Obtener el año actual
                anio := TO_CHAR(CURRENT_DATE, 'YYYY');
                
                -- Obtener el último correlativo para este tipo y año
                -- Formato esperado: PREFIJO-AAAA-SECUEN (ej: SEG-2024-001)
                SELECT COALESCE(MAX(CAST(SPLIT_PART(correlativo, '-', 3) AS INTEGER)), 0) + 1
                INTO secuencia
                FROM proyectos
                WHERE tipo_proyecto_id = NEW.tipo_proyecto_id
                AND SPLIT_PART(correlativo, '-', 2) = anio;
                
                -- Generar el nuevo correlativo
                nuevo_correlativo := prefijo || '-' || anio || '-' || LPAD(secuencia::TEXT, 3, '0');
                
                NEW.correlativo := nuevo_correlativo;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger para ejecutar la función antes de insertar
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
        Schema::dropIfExists('proyectos');
    }
};
