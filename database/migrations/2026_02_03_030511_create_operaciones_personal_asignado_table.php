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
        Schema::create('operaciones_personal_asignado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal');
            $table->foreignId('proyecto_id')->constrained('proyectos');
            $table->foreignId('configuracion_puesto_id')->constrained('proyectos_configuracion_personal');
            $table->foreignId('turno_id')->constrained('turnos');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado_asignacion', 20)->default('activa'); // activa, finalizada, suspendida
            $table->text('motivo_suspension')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            // Indexes for optimizing queries
            $table->index(['personal_id', 'proyecto_id']);
            $table->index(['fecha_inicio', 'fecha_fin']);
            $table->index('estado_asignacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operaciones_personal_asignado');
    }
};
