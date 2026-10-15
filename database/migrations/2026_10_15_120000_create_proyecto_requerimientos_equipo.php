<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proyecto_requerimientos_equipo')) {
            return;
        }

        Schema::create('proyecto_requerimientos_equipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('clave', 40);
            $table->unsignedSmallInteger('cantidad')->default(0);
            $table->boolean('requerido')->default(false);
            $table->timestamps();
            $table->unique(['proyecto_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_requerimientos_equipo');
    }
};
