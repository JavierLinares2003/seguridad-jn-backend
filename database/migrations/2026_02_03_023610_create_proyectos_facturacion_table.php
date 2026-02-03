<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->unique()->constrained('proyectos')->onDelete('cascade');
            $table->foreignId('tipo_documento_facturacion_id')->constrained('tipos_documentos_facturacion');
            $table->string('nit_cliente', 15);
            $table->string('nombre_facturacion', 255);
            $table->text('direccion_facturacion');
            $table->string('forma_pago', 100);
            $table->foreignId('periodicidad_pago_id')->constrained('periodicidades_pago');
            $table->integer('dia_pago')->nullable();
            $table->decimal('monto_proyecto_total', 12, 2)->nullable();
            $table->string('moneda', 3)->default('GTQ');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos_facturacion');
    }
};
