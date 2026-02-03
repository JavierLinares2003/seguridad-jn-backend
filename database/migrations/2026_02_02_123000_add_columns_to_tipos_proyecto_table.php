<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_proyecto', function (Blueprint $table) {
            if (!Schema::hasColumn('tipos_proyecto', 'prefijo_correlativo')) {
                $table->string('prefijo_correlativo', 10)->nullable()->unique();
            }
            if (!Schema::hasColumn('tipos_proyecto', 'descripcion')) {
                $table->text('descripcion')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tipos_proyecto', function (Blueprint $table) {
            $table->dropColumn(['prefijo_correlativo', 'descripcion']);
        });
    }
};
