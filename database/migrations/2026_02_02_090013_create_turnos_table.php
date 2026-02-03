<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->decimal('horas_trabajo', 4, 2);
            $table->text('descripcion')->nullable();
            $table->boolean('requiere_descanso')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
