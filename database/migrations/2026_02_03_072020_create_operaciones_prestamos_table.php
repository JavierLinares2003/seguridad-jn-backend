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
        Schema::create('operaciones_prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->onDelete('cascade');
            $table->decimal('monto_total', 10, 2);
            $table->decimal('saldo_pendiente', 10, 2);
            $table->decimal('tasa_interes', 5, 2)->default(0);
            $table->date('fecha_prestamo')->default(DB::raw('CURRENT_DATE'));
            $table->date('fecha_primer_pago')->nullable();
            $table->integer('cuotas_totales')->nullable();
            $table->integer('cuotas_pagadas')->default(0);
            $table->decimal('monto_cuota', 10, 2)->nullable();
            $table->string('estado_prestamo', 20)->default('activo'); // activo, pagado, cancelado
            $table->text('observaciones')->nullable();
            $table->foreignId('aprobado_por_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes
            $table->index('personal_id');
            $table->index('estado_prestamo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operaciones_prestamos');
    }
};
