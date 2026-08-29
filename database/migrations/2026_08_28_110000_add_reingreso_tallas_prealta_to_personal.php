<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->date('fecha_ingreso_original')->nullable()->after('fecha_inicio');
            $table->date('fecha_reingreso')->nullable()->after('fecha_ingreso_original');
            $table->text('observacion_recontratacion')->nullable()->after('fecha_reingreso');
        });

        DB::statement('
            UPDATE personal
            SET fecha_ingreso_original = fecha_inicio
            WHERE fecha_inicio IS NOT NULL AND fecha_ingreso_original IS NULL
        ');

        Schema::create('personal_tallas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->unique()->constrained('personal')->cascadeOnDelete();
            $table->string('talla_camisa', 10)->nullable();
            $table->string('talla_pantalon', 10)->nullable();
            $table->string('talla_zapato', 10)->nullable();
            $table->string('talla_chaleco', 10)->nullable();
            $table->string('talla_gorra', 10)->nullable();
            $table->string('genero_preferido', 20)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_tallas');

        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_ingreso_original',
                'fecha_reingreso',
                'observacion_recontratacion',
            ]);
        });
    }
};
