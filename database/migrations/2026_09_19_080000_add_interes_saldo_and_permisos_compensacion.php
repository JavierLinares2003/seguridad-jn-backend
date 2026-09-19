<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_permisos', function (Blueprint $table) {
            $table->string('compensa_con', 20)->default('reposicion')->after('observaciones');
            $table->date('fecha_recuperacion')->nullable()->after('compensa_con');
            $table->foreignId('vacacion_id')->nullable()->after('fecha_recuperacion')
                ->constrained('personal_vacaciones')->nullOnDelete();
        });

        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->unsignedInteger('cuotas_totales')->nullable()->after('monto_total');
            $table->decimal('monto_cuota', 12, 2)->nullable()->after('cuotas_totales');
            $table->date('fecha_primer_pago')->nullable()->after('fecha_entrega');
            $table->uuid('grupo_descuento_faltante')->nullable()->after('grupo_uniforme');
        });

        Schema::table('bodega_entrega_items', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_no_devuelta')->default(0)->after('cantidad_devuelta');
        });

        // Préstamos activos sin abonos: el saldo debe incluir el interés.
        DB::statement("
            UPDATE operaciones_prestamos
            SET saldo_pendiente = ROUND(monto_total * (1 + COALESCE(tasa_interes, 0) / 100.0), 2)
            WHERE estado_prestamo = 'activo'
              AND COALESCE(tasa_interes, 0) > 0
              AND ABS(saldo_pendiente - monto_total) < 0.01
        ");
    }

    public function down(): void
    {
        Schema::table('personal_permisos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vacacion_id');
            $table->dropColumn(['compensa_con', 'fecha_recuperacion']);
        });

        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->dropColumn(['cuotas_totales', 'monto_cuota', 'fecha_primer_pago', 'grupo_descuento_faltante']);
        });

        Schema::table('bodega_entrega_items', function (Blueprint $table) {
            $table->dropColumn('cantidad_no_devuelta');
        });
    }
};
