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
        Schema::table('proyectos_facturacion', function (Blueprint $table) {
            $table->boolean('aplica_impuesto')->default(false)->after('moneda');
            $table->decimal('porcentaje_impuesto', 5, 2)->nullable()->after('aplica_impuesto')->comment('Ej: 12.00 para IVA 12%');
            $table->decimal('monto_impuesto', 12, 2)->nullable()->after('porcentaje_impuesto')->comment('monto_proyecto_total * porcentaje_impuesto / 100');
            $table->decimal('monto_total_con_impuesto', 12, 2)->nullable()->after('monto_impuesto')->comment('monto_proyecto_total + monto_impuesto');
        });
    }

    public function down(): void
    {
        Schema::table('proyectos_facturacion', function (Blueprint $table) {
            $table->dropColumn(['aplica_impuesto', 'porcentaje_impuesto', 'monto_impuesto', 'monto_total_con_impuesto']);
        });
    }
};
