<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones_transacciones', function (Blueprint $table) {
            $table->uuid('grupo_uniforme')->nullable()->after('prestamo_id');
            $table->unsignedSmallInteger('numero_cuota')->nullable()->after('grupo_uniforme');
            $table->unsignedSmallInteger('cuotas_totales')->nullable()->after('numero_cuota');
            $table->decimal('monto_total_uniforme', 10, 2)->nullable()->after('cuotas_totales');
            $table->decimal('saldo_despues', 10, 2)->nullable()->after('monto_total_uniforme');

            $table->index('grupo_uniforme');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones_transacciones', function (Blueprint $table) {
            $table->dropIndex(['grupo_uniforme']);
            $table->dropColumn([
                'grupo_uniforme',
                'numero_cuota',
                'cuotas_totales',
                'monto_total_uniforme',
                'saldo_despues',
            ]);
        });
    }
};
