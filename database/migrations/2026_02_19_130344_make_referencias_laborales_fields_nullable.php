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
        Schema::table('personal_referencias_laborales', function (Blueprint $table) {
            $table->string('telefono', 15)->nullable()->change();
            $table->date('fecha_inicio')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_referencias_laborales', function (Blueprint $table) {
            $table->string('telefono', 15)->nullable(false)->change();
            $table->date('fecha_inicio')->nullable(false)->change();
        });
    }
};
