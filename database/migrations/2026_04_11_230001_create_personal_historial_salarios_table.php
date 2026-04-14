<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_historial_salarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')
                ->constrained('personal')
                ->cascadeOnDelete();
            $table->decimal('salario_anterior', 10, 2);
            $table->decimal('salario_nuevo', 10, 2);
            $table->date('fecha_cambio');
            $table->foreignId('cambiado_por')
                ->nullable()
                ->nullOnDelete()
                ->constrained('users');
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->index('personal_id');
            $table->index('fecha_cambio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_historial_salarios');
    }
};
