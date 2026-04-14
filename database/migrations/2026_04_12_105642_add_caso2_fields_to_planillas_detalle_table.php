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
            // Caso 2: días de descanso pagados y días ausentes con penalidad
            $table->integer('dias_descanso')->default(0)->after('dias_trabajados');
            $table->integer('dias_ausentes')->default(0)->after('dias_descanso');
            // Penalidad automática por ausencias (50% de la tarifa diaria por cada día ausente)
            $table->decimal('descuento_ausencias', 10, 2)->default(0)->after('otros_descuentos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planillas_detalle', function (Blueprint $table) {
            $table->dropColumn(['dias_descanso', 'dias_ausentes', 'descuento_ausencias']);
        });
    }
};
