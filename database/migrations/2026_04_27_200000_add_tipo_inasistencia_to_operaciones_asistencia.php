<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->string('tipo_inasistencia', 10)->nullable()->after('tipo_ausencia');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->dropColumn('tipo_inasistencia');
        });
    }
};
