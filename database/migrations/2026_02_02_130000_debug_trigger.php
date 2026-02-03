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
            BEGIN
                NEW.correlativo := 'DEBUG-' || TO_CHAR(CURRENT_TIMESTAMP, 'HH24MISS');
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
        // No debug rollback needed really
    }
};
