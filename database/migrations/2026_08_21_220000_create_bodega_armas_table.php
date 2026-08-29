<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bodega_armas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->nullable()->unique();
            $table->string('codigo_interno', 40)->nullable()->unique();
            $table->string('tipo', 20); // revolver, 9mm, escopeta
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('serie', 80);
            $table->string('tenencia', 80)->nullable();
            $table->string('portacion', 80)->nullable();
            $table->date('vencimiento')->nullable();
            $table->string('responsable_nombre', 150)->nullable();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('estado', 20)->default('en_bodega'); // en_bodega, asignada, mantenimiento, baja
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique('serie');
            $table->index(['tipo', 'estado']);
            $table->index('vencimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bodega_armas');
    }
};
