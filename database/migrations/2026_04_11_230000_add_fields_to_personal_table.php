<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->foreignId('nivel_estudio_id')
                ->nullable()
                ->nullOnDelete()
                ->constrained('niveles_estudio')
                ->after('departamento_id');

            $table->boolean('tiene_igss')->default(false)->after('salario_base');
            $table->boolean('tiene_prestaciones')->default(false)->after('tiene_igss');
            $table->boolean('tiene_bono14')->default(false)->after('tiene_prestaciones');
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropForeign(['nivel_estudio_id']);
            $table->dropColumn(['nivel_estudio_id', 'tiene_igss', 'tiene_prestaciones', 'tiene_bono14']);
        });
    }
};
