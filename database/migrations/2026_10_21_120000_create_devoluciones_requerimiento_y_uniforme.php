<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proyecto_requerimiento_devoluciones')) {
            Schema::create('proyecto_requerimiento_devoluciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
                $table->date('fecha');
                $table->text('nombres')->nullable();
                $table->text('observaciones')->nullable();
                $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('proyecto_requerimiento_devolucion_items')) {
            Schema::create('proyecto_requerimiento_devolucion_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('devolucion_id')->constrained('proyecto_requerimiento_devoluciones')->cascadeOnDelete();
                $table->string('clave', 40);
                $table->boolean('devuelto')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bodega_devoluciones_uniforme')) {
            Schema::create('bodega_devoluciones_uniforme', function (Blueprint $table) {
                $table->id();
                $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
                $table->string('nombre_persona');
                $table->date('fecha');
                $table->json('prendas');
                $table->text('observaciones')->nullable();
                $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bodega_devoluciones_uniforme');
        Schema::dropIfExists('proyecto_requerimiento_devolucion_items');
        Schema::dropIfExists('proyecto_requerimiento_devoluciones');
    }
};
