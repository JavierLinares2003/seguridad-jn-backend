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
        Schema::create('planillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_planilla');
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->decimal('total_devengado', 12, 2)->default(0);
            $table->decimal('total_descuentos', 12, 2)->default(0);
            $table->decimal('total_neto', 12, 2)->default(0);
            $table->enum('estado_planilla', ['borrador', 'revision', 'aprobada', 'pagada', 'cancelada'])
                ->default('borrador');
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->foreignId('aprobado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index(['periodo_inicio', 'periodo_fin']);
            $table->index('estado_planilla');
            
            // No permitir duplicar períodos
            $table->unique(['periodo_inicio', 'periodo_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planillas');
    }
};
