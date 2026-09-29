<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('personal_permisos', 'compensa_con')) {
            Schema::table('personal_permisos', function (Blueprint $table) {
                $table->string('compensa_con', 20)->default('reposicion')->after('observaciones');
            });
        }

        if (! Schema::hasColumn('personal_permisos', 'fecha_recuperacion')) {
            Schema::table('personal_permisos', function (Blueprint $table) {
                $table->date('fecha_recuperacion')->nullable()->after('compensa_con');
            });
        }

        if (! Schema::hasColumn('personal_permisos', 'vacacion_id')) {
            Schema::table('personal_permisos', function (Blueprint $table) {
                $table->foreignId('vacacion_id')->nullable()->after('fecha_recuperacion')
                    ->constrained('personal_vacaciones')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('personal_permiso_fechas_reposicion')) {
            Schema::create('personal_permiso_fechas_reposicion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('personal_permiso_id')
                    ->constrained('personal_permisos')
                    ->cascadeOnDelete();
                $table->date('fecha');
                $table->decimal('horas', 8, 2);
                $table->timestamps();

                $table->index(['personal_permiso_id', 'fecha']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_permiso_fechas_reposicion');

        if (Schema::hasColumn('personal_permisos', 'vacacion_id')) {
            Schema::table('personal_permisos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vacacion_id');
            });
        }

        if (Schema::hasColumn('personal_permisos', 'fecha_recuperacion')) {
            Schema::table('personal_permisos', function (Blueprint $table) {
                $table->dropColumn('fecha_recuperacion');
            });
        }

        if (Schema::hasColumn('personal_permisos', 'compensa_con')) {
            Schema::table('personal_permisos', function (Blueprint $table) {
                $table->dropColumn('compensa_con');
            });
        }
    }
};
