<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacaciones_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')
                ->nullable()
                ->nullOnDelete()
                ->constrained('departamentos');
            $table->integer('dias_por_anio')->default(8);
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->unique('departamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones_config');
    }
};
