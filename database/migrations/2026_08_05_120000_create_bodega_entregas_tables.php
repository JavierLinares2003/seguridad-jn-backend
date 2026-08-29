<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bodega_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->restrictOnDelete();
            $table->string('tipo', 20); // simple, kit, reposicion
            $table->boolean('cobrar')->default(false);
            $table->decimal('monto_total', 12, 2)->default(0);
            $table->string('motivo_reposicion', 80)->nullable();
            $table->text('observaciones')->nullable();
            $table->date('fecha_entrega');
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('grupo_uniforme')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'fecha_entrega']);
            $table->index('personal_id');
        });

        Schema::create('bodega_entrega_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('bodega_entregas')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('bodega_variantes')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->foreignId('movimiento_id')->nullable()->constrained('bodega_movimientos')->nullOnDelete();
            $table->timestamps();

            $table->index('entrega_id');
        });

        Schema::table('bodega_movimientos', function (Blueprint $table) {
            $table->foreignId('entrega_id')->nullable()->after('observaciones')
                ->constrained('bodega_entregas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bodega_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrega_id');
        });
        Schema::dropIfExists('bodega_entrega_items');
        Schema::dropIfExists('bodega_entregas');
    }
};
