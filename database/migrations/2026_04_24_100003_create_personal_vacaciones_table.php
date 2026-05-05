<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')
                ->constrained('personal')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('anio')->default(now()->year);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->integer('dias_solicitados');
            $table->integer('dias_aprobados');
            $table->text('descripcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('documento_ruta')->nullable();
            $table->string('documento_nombre_original')->nullable();
            $table->string('documento_extension', 10)->nullable();
            $table->integer('documento_tamanio_kb')->nullable();
            $table->foreignId('registrado_por_user_id')
                ->nullable()
                ->nullOnDelete()
                ->constrained('users');
            $table->timestamps();

            $table->index('personal_id');
            $table->index('anio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_vacaciones');
    }
};
