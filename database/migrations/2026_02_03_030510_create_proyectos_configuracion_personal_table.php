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
        Schema::create('proyectos_configuracion_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('nombre_puesto', 100);
            $table->integer('cantidad_requerida');
            $table->integer('edad_minima');
            $table->integer('edad_maxima');
            $table->foreignId('sexo_id')->nullable()->constrained('sexos')->comment('NULL = indistinto');
            $table->decimal('altura_minima', 5, 2)->nullable()->comment('En metros');
            $table->foreignId('estudio_minimo_id')->nullable()->constrained('niveles_estudio');
            $table->foreignId('tipo_personal_id')->constrained('tipos_personal');
            $table->foreignId('turno_id')->constrained('turnos');
            $table->decimal('costo_hora_proyecto', 10, 2)->comment('Lo que cobra la empresa');
            $table->decimal('pago_hora_personal', 10, 2)->comment('Lo que se le paga al empleado');
            
            // Generated Column for Margin
            // Formula: ((Price - Cost) / Price) * 100
            $table->decimal('margen_utilidad', 5, 2)
                  ->storedAs('CASE WHEN costo_hora_proyecto > 0 THEN ((costo_hora_proyecto - pago_hora_personal) / costo_hora_proyecto) * 100 ELSE 0 END')
                  ->comment('Calculado automáticamente: ((Costo - Pago) / Costo) * 100');

            $table->string('estado', 20)->default('activo'); // activo, inactivo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyectos_configuracion_personal');
    }
};
