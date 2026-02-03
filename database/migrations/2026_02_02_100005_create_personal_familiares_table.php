<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_familiares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('parentesco_id')->constrained('catalogo_parentescos')->cascadeOnDelete();
            $table->string('nombre_completo', 200);
            $table->string('telefono', 15);
            $table->boolean('es_contacto_emergencia')->default(false);
            $table->timestamps();

            $table->index('personal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_familiares');
    }
};
