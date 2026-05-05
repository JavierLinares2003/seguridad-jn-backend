<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->foreignId('permiso_ausencia_id')
                ->nullable()
                ->after('tipo_inasistencia')
                ->constrained('personal_permisos')
                ->nullOnDelete();

            $table->foreignId('permiso_reposicion_id')
                ->nullable()
                ->after('permiso_ausencia_id')
                ->constrained('personal_permisos')
                ->nullOnDelete();

            $table->decimal('horas_reposicion', 5, 2)
                ->nullable()
                ->after('permiso_reposicion_id');

            $table->index('permiso_ausencia_id');
            $table->index('permiso_reposicion_id');
        });
    }

    public function down(): void
    {
        Schema::table('operaciones_asistencia', function (Blueprint $table) {
            $table->dropConstrainedForeignId('permiso_ausencia_id');
            $table->dropConstrainedForeignId('permiso_reposicion_id');
            $table->dropColumn('horas_reposicion');
        });
    }
};
