<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            // Campos para control de ausencias
            $table->foreignId('motivo_ausencia_id')
                  ->nullable()
                  ->after('es_descanso')
                  ->constrained('catalogo_motivos_ausencia')
                  ->nullOnDelete();
            $table->text('descripcion_ausencia')->nullable()->after('motivo_ausencia_id');
            $table->string('tipo_ausencia', 20)->nullable()->after('descripcion_ausencia')
                  ->comment('justificada, injustificada');
            $table->boolean('es_ausente')->default(false)->after('tipo_ausencia')
                  ->comment('True si el agente no se presentó');

            // Campo para vincular con planilla (cuando se procesa)
            $table->foreignId('planilla_id')
                  ->nullable()
                  ->after('registrado_por_user_id')
                  ->constrained('planillas')
                  ->nullOnDelete();
            $table->boolean('procesado_planilla')->default(false)->after('planilla_id')
                  ->comment('True si ya fue incluido en una planilla');

            // Índices
            $table->index('motivo_ausencia_id');
            $table->index('planilla_id');
            $table->index('es_ausente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->dropForeign(['motivo_ausencia_id']);
            $table->dropForeign(['planilla_id']);
            $table->dropColumn([
                'motivo_ausencia_id',
                'descripcion_ausencia',
                'tipo_ausencia',
                'es_ausente',
                'planilla_id',
                'procesado_planilla'
            ]);
        });
    }
};
