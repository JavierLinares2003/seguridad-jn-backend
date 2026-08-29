<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bodega_facturas_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('bodega_proveedores')->restrictOnDelete();
            $table->date('fecha_factura');
            $table->string('serie', 40)->nullable();
            $table->string('numero_factura', 60);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('fecha_factura');
        });

        // PostgreSQL trata NULL como distinto en UNIQUE; COALESCE evita duplicar factura sin serie.
        DB::statement("
            CREATE UNIQUE INDEX bodega_facturas_compra_doc_unique
            ON bodega_facturas_compra (proveedor_id, COALESCE(serie, ''), numero_factura)
        ");

        Schema::create('bodega_factura_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('bodega_facturas_compra')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('bodega_variantes')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->foreignId('movimiento_id')->nullable()->constrained('bodega_movimientos')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('bodega_movimientos', function (Blueprint $table) {
            $table->foreignId('factura_compra_id')->nullable()->after('entrega_id')
                ->constrained('bodega_facturas_compra')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bodega_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factura_compra_id');
        });
        Schema::dropIfExists('bodega_factura_compra_items');
        Schema::dropIfExists('bodega_facturas_compra');
    }
};
