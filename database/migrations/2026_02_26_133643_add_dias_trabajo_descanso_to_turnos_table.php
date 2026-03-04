<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->integer('dias_trabajo')->default(1)->after('horas_trabajo')->comment('Días consecutivos de trabajo');
            $table->integer('dias_descanso')->default(1)->after('dias_trabajo')->comment('Días consecutivos de descanso');
        });

        // Update existing turnos with correct values
        DB::table('turnos')->where('nombre', '8x8')->update(['dias_trabajo' => 1, 'dias_descanso' => 0]); // Trabajo diario
        DB::table('turnos')->where('nombre', '12x12')->update(['dias_trabajo' => 1, 'dias_descanso' => 0]); // Trabajo diario
        DB::table('turnos')->where('nombre', '12x24')->update(['dias_trabajo' => 1, 'dias_descanso' => 1]);
        DB::table('turnos')->where('nombre', '24x24')->update(['dias_trabajo' => 1, 'dias_descanso' => 1]);
        DB::table('turnos')->where('nombre', '24x48')->update(['dias_trabajo' => 1, 'dias_descanso' => 2]);
        DB::table('turnos')->where('nombre', '24x72')->update(['dias_trabajo' => 1, 'dias_descanso' => 3]);
        DB::table('turnos')->where('nombre', '48x48')->update(['dias_trabajo' => 2, 'dias_descanso' => 2]);
        DB::table('turnos')->where('nombre', '48x72')->update(['dias_trabajo' => 2, 'dias_descanso' => 3]);
        DB::table('turnos')->where('nombre', '72x72')->update(['dias_trabajo' => 3, 'dias_descanso' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn(['dias_trabajo', 'dias_descanso']);
        });
    }
};
