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
        Schema::create('operaciones_transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->onDelete('cascade');
            $table->foreignId('asistencia_id')->nullable()->constrained('operaciones_asistencia')->onDelete('set null');
            $table->string('tipo_transaccion', 30); // multa, uniforme, anticipo, prestamo, abono_prestamo, antecedentes, otro_descuento
            $table->decimal('monto', 10, 2);
            $table->text('descripcion');
            $table->date('fecha_transaccion')->default(DB::raw('CURRENT_DATE'));
            $table->boolean('es_descuento')->default(true); // TRUE=descuento, FALSE=abono
            $table->string('estado_transaccion', 20)->default('pendiente'); // pendiente, aplicado, cancelado
            $table->foreignId('prestamo_id')->nullable()->constrained('operaciones_prestamos')->onDelete('set null');
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes
            $table->index('personal_id');
            $table->index('asistencia_id');
            $table->index('tipo_transaccion');
            $table->index('fecha_transaccion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operaciones_transacciones');
    }
};
