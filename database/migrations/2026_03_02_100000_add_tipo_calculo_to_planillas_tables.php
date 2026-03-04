<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos para soportar el Strategy Pattern de cálculo de planillas:
     * - tipo_calculo: identifica qué estrategia se usó para calcular
     * - horas_por_turno: horas de trabajo según el turno asignado
     */
    public function up(): void
    {
        // Agregar tipo_calculo a tabla planillas
        Schema::table('planillas', function (Blueprint $table) {
            $table->string('tipo_calculo', 50)->default('caso_1')->after('observaciones');
        });

        // Agregar campos a tabla planillas_detalle
        Schema::table('planillas_detalle', function (Blueprint $table) {
            $table->decimal('horas_por_turno', 5, 2)->nullable()->after('horas_trabajadas');
            $table->string('tipo_calculo', 50)->default('caso_1')->after('observaciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planillas', function (Blueprint $table) {
            $table->dropColumn('tipo_calculo');
        });

        Schema::table('planillas_detalle', function (Blueprint $table) {
            $table->dropColumn(['horas_por_turno', 'tipo_calculo']);
        });
    }
};
