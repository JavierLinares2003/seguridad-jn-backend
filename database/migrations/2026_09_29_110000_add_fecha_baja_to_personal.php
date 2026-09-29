<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('personal', 'fecha_baja')) {
            Schema::table('personal', function (Blueprint $table) {
                $table->date('fecha_baja')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('personal', 'fecha_baja')) {
            Schema::table('personal', function (Blueprint $table) {
                $table->dropColumn('fecha_baja');
            });
        }
    }
};
