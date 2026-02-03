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
        Schema::create('proyectos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('codigo_inventario', 50);
            $table->string('nombre_item', 200);
            $table->integer('cantidad_asignada');
            $table->string('estado_item', 20)->default('asignado'); // asignado, en_uso, devuelto, dañado
            $table->date('fecha_asignacion')->default(DB::raw('CURRENT_DATE'));
            $table->date('fecha_devolucion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['proyecto_id', 'codigo_inventario']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyectos_inventario');
    }
};
