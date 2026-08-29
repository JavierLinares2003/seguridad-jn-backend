<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bodega_compras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('proveedor_id')->nullable()->constrained('bodega_proveedores')->nullOnDelete();
            $table->string('estado', 30)->default('solicitud');
            $table->date('fecha_solicitud');
            $table->date('fecha_cotizacion')->nullable();
            $table->date('fecha_aprobacion')->nullable();
            $table->date('fecha_anticipo_pagado')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->date('fecha_saldo_pagado')->nullable();
            $table->decimal('total_estimado', 12, 2)->default(0);
            $table->decimal('total_final', 12, 2)->nullable();
            $table->unsignedTinyInteger('anticipo_porcentaje')->default(50);
            $table->boolean('anticipo_pagado')->default(false);
            $table->boolean('saldo_pagado')->default(false);
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('factura_compra_id')->nullable()->constrained('bodega_facturas_compra')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha_solicitud']);
        });

        Schema::create('bodega_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('bodega_compras')->cascadeOnDelete();
            $table->string('descripcion', 200);
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_estimado', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('bodega_facturas_compra', function (Blueprint $table) {
            $table->foreignId('compra_id')
                ->nullable()
                ->after('proveedor_id')
                ->constrained('bodega_compras')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bodega_facturas_compra', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compra_id');
        });
        Schema::dropIfExists('bodega_compra_items');
        Schema::dropIfExists('bodega_compras');
    }
};
