<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_proyecto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('prefijo_correlativo', 10)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_proyecto');
    }
};
