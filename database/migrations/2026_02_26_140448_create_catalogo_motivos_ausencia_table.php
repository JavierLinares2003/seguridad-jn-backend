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
        Schema::create('catalogo_motivos_ausencia', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('es_justificada')->default(false)->comment('Si es true, no aplica descuento');
            $table->boolean('aplica_descuento')->default(true)->comment('Si aplica descuento en planilla');
            $table->boolean('requiere_documento')->default(false)->comment('Si requiere documento de soporte');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_motivos_ausencia');
    }
};
