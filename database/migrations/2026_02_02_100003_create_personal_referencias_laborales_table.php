<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_referencias_laborales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->string('nombre_empresa', 200);
            $table->string('puesto_ocupado', 100);
            $table->string('telefono', 15);
            $table->text('direccion')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('motivo_retiro')->nullable();
            $table->timestamps();

            $table->index('personal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_referencias_laborales');
    }
};
