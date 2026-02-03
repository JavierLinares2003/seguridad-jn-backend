<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_redes_sociales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('icono', 50)->nullable();
            $table->string('url_base', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_redes_sociales');
    }
};
