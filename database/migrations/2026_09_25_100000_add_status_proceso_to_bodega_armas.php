<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodega_armas', function (Blueprint $table) {
            $table->string('status_proceso', 30)->default('sin_proceso');
        });
    }

    public function down(): void
    {
        Schema::table('bodega_armas', function (Blueprint $table) {
            $table->dropColumn('status_proceso');
        });
    }
};
