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
        Schema::table('operaciones_transacciones', function (Blueprint $table) {
            $table->string('comprobante_ruta', 500)->nullable()->after('registrado_por_user_id');
            $table->string('comprobante_nombre_original', 255)->nullable()->after('comprobante_ruta');
            $table->string('comprobante_extension', 10)->nullable()->after('comprobante_nombre_original');
            $table->integer('comprobante_tamanio_kb')->nullable()->after('comprobante_extension');
            $table->foreignId('comprobante_subido_por_user_id')->nullable()->after('comprobante_tamanio_kb')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operaciones_transacciones', function (Blueprint $table) {
            $table->dropForeign(['comprobante_subido_por_user_id']);
            $table->dropColumn([
                'comprobante_ruta',
                'comprobante_nombre_original',
                'comprobante_extension',
                'comprobante_tamanio_kb',
                'comprobante_subido_por_user_id',
            ]);
        });
    }
};
