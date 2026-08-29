<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bodega_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('codigo', 50)->unique();
            $table->string('icono', 50)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('bodega_proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('contacto', 150)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('bodega_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('bodega_categorias')->cascadeOnDelete();
            $table->string('nombre', 200);
            $table->string('unidad', 40)->default('unidad');
            $table->boolean('usa_talla')->default(false);
            $table->boolean('usa_condicion')->default(false);
            $table->boolean('usa_genero')->default(false);
            $table->boolean('es_uniforme')->default(false);
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['categoria_id', 'nombre']);
        });

        Schema::create('bodega_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('bodega_productos')->cascadeOnDelete();
            $table->string('talla', 30)->nullable();
            $table->string('condicion', 20)->nullable(); // nuevo, usado
            $table->string('genero', 20)->nullable(); // mujer, hombre, unisex
            $table->string('sku', 80)->nullable()->unique();
            $table->integer('existencia')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(
                ['producto_id', 'talla', 'condicion', 'genero'],
                'bodega_variantes_unique_attrs'
            );
            $table->index('existencia');
        });

        Schema::create('bodega_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('bodega_variantes')->restrictOnDelete();
            $table->string('tipo', 20); // ingreso, egreso, ajuste, ajuste_inicial
            $table->integer('cantidad'); // siempre positiva; el tipo define el sentido
            $table->integer('existencia_anterior')->default(0);
            $table->integer('existencia_nueva')->default(0);
            $table->date('fecha_movimiento');
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('bodega_proveedores')->nullOnDelete();
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referencia', 120)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'fecha_movimiento']);
            $table->index('personal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bodega_movimientos');
        Schema::dropIfExists('bodega_variantes');
        Schema::dropIfExists('bodega_productos');
        Schema::dropIfExists('bodega_proveedores');
        Schema::dropIfExists('bodega_categorias');
    }
};
