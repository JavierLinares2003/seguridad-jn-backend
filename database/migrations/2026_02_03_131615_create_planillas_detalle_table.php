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
        Schema::create('planillas_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_id')->constrained('planillas')->onDelete('cascade');
            $table->foreignId('personal_id')->constrained('personal');
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos');
            
            // Días y horas
            $table->integer('dias_trabajados')->default(0);
            $table->decimal('horas_trabajadas', 8, 2)->default(0);
            $table->decimal('pago_por_hora', 10, 2)->default(0);
            
            // Devengado
            $table->decimal('salario_devengado', 10, 2)->default(0);
            $table->decimal('bonificacion', 10, 2)->default(0);
            $table->decimal('horas_extra', 10, 2)->default(0);
            
            // Descuentos
            $table->decimal('descuento_multas', 10, 2)->default(0);
            $table->decimal('descuento_uniformes', 10, 2)->default(0);
            $table->decimal('descuento_anticipos', 10, 2)->default(0);
            $table->decimal('descuento_prestamos', 10, 2)->default(0);
            $table->decimal('descuento_antecedentes', 10, 2)->default(0);
            $table->decimal('otros_descuentos', 10, 2)->default(0);
            $table->decimal('total_descuentos', 10, 2)->default(0);
            
            // Neto
            $table->decimal('salario_neto', 10, 2)->default(0);
            
            $table->text('observaciones')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('planilla_id');
            $table->index('personal_id');
            
            // Un empleado solo puede aparecer una vez por planilla
            $table->unique(['planilla_id', 'personal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planillas_detalle');
    }
};
