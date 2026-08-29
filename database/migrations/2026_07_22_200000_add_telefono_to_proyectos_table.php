<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyectos', 'telefono')) {
                $table->string('telefono', 20)->nullable()->after('empresa_cliente');
            }
            if (!Schema::hasColumn('proyectos', 'telefono_validado')) {
                $table->boolean('telefono_validado')->default(false)->after('telefono');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('proyectos', 'telefono_validado')) {
                $table->dropColumn('telefono_validado');
            }
            if (Schema::hasColumn('proyectos', 'telefono')) {
                $table->dropColumn('telefono');
            }
        });
    }
};
