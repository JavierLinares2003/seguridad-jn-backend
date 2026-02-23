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
        Schema::table('personal', function (Blueprint $table) {
            $table->string('banco', 100)->nullable()->after('tipo_pago_id');
            $table->string('tipo_cuenta', 20)->nullable()->after('banco')->comment('ahorro, corriente, monetaria');
            $table->string('numero_cuenta', 50)->nullable()->after('tipo_cuenta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn(['banco', 'tipo_cuenta', 'numero_cuenta']);
        });
    }
};
