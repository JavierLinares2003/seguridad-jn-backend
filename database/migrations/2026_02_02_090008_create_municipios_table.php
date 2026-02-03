<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_geo_id')->constrained('departamentos_geograficos')->onDelete('restrict');
            $table->string('codigo', 4)->unique();
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('departamento_geo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
