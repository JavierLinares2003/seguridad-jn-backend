<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->enum('tipo', ['horas', 'dias']);
            $table->decimal('cantidad_aprobada', 8, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('descripcion');
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('documento_ruta')->nullable();
            $table->string('documento_nombre_original')->nullable();
            $table->string('documento_extension')->nullable();
            $table->integer('documento_tamanio_kb')->nullable();
            $table->timestamps();

            $table->index('personal_id');
            $table->index('fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_permisos');
    }
};
