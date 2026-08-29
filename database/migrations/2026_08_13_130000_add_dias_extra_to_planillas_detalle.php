<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planillas_detalle', function (Blueprint $table) {
            $table->unsignedInteger('dias_extra')->default(0)->after('dias_ausentes');
        });
    }

    public function down(): void
    {
        Schema::table('planillas_detalle', function (Blueprint $table) {
            $table->dropColumn('dias_extra');
        });
    }
};
