<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->string('modo_entrega', 20)->default('directa')->after('personal_id');
            $table->foreignId('personal_operaciones_id')
                ->nullable()
                ->after('modo_entrega')
                ->constrained('personal')
                ->nullOnDelete();
            $table->boolean('cambio_por_dano')->default(false)->after('motivo_reposicion');
            $table->foreignId('variante_entrada_dano_id')
                ->nullable()
                ->after('cambio_por_dano')
                ->constrained('bodega_variantes')
                ->nullOnDelete();
            $table->unsignedInteger('cantidad_entrada_dano')->nullable()->after('variante_entrada_dano_id');
        });
    }

    public function down(): void
    {
        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variante_entrada_dano_id');
            $table->dropConstrainedForeignId('personal_operaciones_id');
            $table->dropColumn([
                'modo_entrega',
                'cambio_por_dano',
                'cantidad_entrada_dano',
            ]);
        });
    }
};
