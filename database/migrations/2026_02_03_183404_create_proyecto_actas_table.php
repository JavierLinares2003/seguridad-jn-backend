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
        Schema::create('proyecto_actas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('tipo_documento')->default('acta_inicio');
            $table->string('nombre_firmante');
            $table->string('dpi_firmante');
            $table->string('puesto_firmante');
            $table->date('fecha_firma');
            $table->date('fecha_inicio_servicios');
            $table->string('archivo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyecto_actas');
    }
};
