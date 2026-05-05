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
        Schema::table('planillas_detalle', function (Blueprint $table) {
            $table->decimal('descuento_igss', 10, 2)->default(0)->after('descuento_ausencias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planillas_detalle', function (Blueprint $table) {
            $table->dropColumn('descuento_igss');
        });
    }
};
