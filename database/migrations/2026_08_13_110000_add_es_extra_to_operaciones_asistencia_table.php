<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->boolean('es_extra')->default(false)->after('es_descanso');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->dropColumn('es_extra');
        });
    }
};
