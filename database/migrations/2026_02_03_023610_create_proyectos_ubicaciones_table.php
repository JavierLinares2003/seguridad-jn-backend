<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->unique()->constrained('proyectos')->onDelete('cascade');
            $table->foreignId('departamento_geo_id')->constrained('departamentos_geograficos');
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->integer('zona')->nullable();
            $table->text('direccion_completa');
            // $table->specificType('coordenadas_gps', 'point')->nullable(); 
            $table->timestamps();
        });

        // Add point column using raw SQL as specificType might not be available or problematic
        DB::statement('ALTER TABLE proyectos_ubicaciones ADD COLUMN coordenadas_gps point');
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos_ubicaciones');
    }
};
