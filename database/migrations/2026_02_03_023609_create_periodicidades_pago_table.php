<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodicidades_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->integer('dias');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodicidades_pago');
    }
};
