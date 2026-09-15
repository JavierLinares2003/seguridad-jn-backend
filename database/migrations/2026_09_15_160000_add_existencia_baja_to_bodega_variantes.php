<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodega_variantes', function (Blueprint $table) {
            if (!Schema::hasColumn('bodega_variantes', 'existencia_baja')) {
                $table->unsignedInteger('existencia_baja')->default(0)->after('existencia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bodega_variantes', function (Blueprint $table) {
            if (Schema::hasColumn('bodega_variantes', 'existencia_baja')) {
                $table->dropColumn('existencia_baja');
            }
        });
    }
};
