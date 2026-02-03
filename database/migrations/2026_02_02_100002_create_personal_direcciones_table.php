<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->unique()->constrained('personal')->cascadeOnDelete();
            $table->foreignId('departamento_geo_id')->nullable()->constrained('departamentos_geograficos')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->integer('zona')->nullable()->comment('1-25');
            $table->text('direccion_completa');
            $table->boolean('es_direccion_actual')->default(true);
            $table->timestamps();

            $table->index('personal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_direcciones');
    }
};
