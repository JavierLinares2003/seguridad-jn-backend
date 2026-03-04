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
        Schema::table('planillas', function (Blueprint $table) {
            // Agregar columnas de ámbito
            $table->foreignId('proyecto_id')
                ->nullable()
                ->after('observaciones')
                ->constrained('proyectos')
                ->onDelete('restrict');

            $table->foreignId('departamento_id')
                ->nullable()
                ->after('proyecto_id')
                ->constrained('departamentos')
                ->onDelete('restrict');

            // Índices para mejorar consultas
            $table->index('proyecto_id');
            $table->index('departamento_id');
        });

        // Eliminar el índice único existente (periodo_inicio, periodo_fin)
        Schema::table('planillas', function (Blueprint $table) {
            $table->dropUnique(['periodo_inicio', 'periodo_fin']);
        });

        // Crear nuevo índice único que incluye el ámbito
        // NULL se maneja especialmente: dos NULLs se consideran diferentes en PostgreSQL
        // Usamos COALESCE para convertir NULL a 0 y así poder comparar correctamente
        DB::statement('
            CREATE UNIQUE INDEX planillas_periodo_ambito_unique
            ON planillas (
                periodo_inicio,
                periodo_fin,
                COALESCE(proyecto_id, 0),
                COALESCE(departamento_id, 0)
            )
            WHERE estado_planilla NOT IN (\'cancelada\')
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar índice único con ámbito
        DB::statement('DROP INDEX IF EXISTS planillas_periodo_ambito_unique');

        Schema::table('planillas', function (Blueprint $table) {
            // Restaurar índice único original
            $table->unique(['periodo_inicio', 'periodo_fin']);

            // Eliminar columnas de ámbito
            $table->dropForeign(['proyecto_id']);
            $table->dropForeign(['departamento_id']);
            $table->dropColumn(['proyecto_id', 'departamento_id']);
        });
    }
};
