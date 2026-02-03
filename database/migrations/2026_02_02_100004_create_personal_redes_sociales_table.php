<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_redes_sociales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('red_social_id')->constrained('catalogo_redes_sociales')->cascadeOnDelete();
            $table->string('nombre_usuario', 100);
            $table->string('url_perfil', 255)->nullable();
            $table->timestamps();

            $table->index('personal_id');
            $table->unique(['personal_id', 'red_social_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_redes_sociales');
    }
};
