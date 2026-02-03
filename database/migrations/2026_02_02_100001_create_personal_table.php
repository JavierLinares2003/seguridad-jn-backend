<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('dpi', 13)->unique();
            $table->string('nit', 15)->unique()->nullable();
            $table->string('email', 150)->unique();
            $table->string('telefono', 15);
            $table->string('numero_igss', 50)->nullable();
            $table->date('fecha_nacimiento');
            $table->foreignId('estado_civil_id')->nullable()->constrained('estados_civiles')->nullOnDelete();
            $table->decimal('altura', 5, 2)->comment('En metros');
            $table->foreignId('tipo_sangre_id')->nullable()->constrained('tipos_sangre')->nullOnDelete();
            $table->decimal('peso', 5, 2)->comment('En libras');
            $table->boolean('sabe_leer')->default(true);
            $table->boolean('sabe_escribir')->default(true);
            $table->boolean('es_alergico')->default(false);
            $table->text('alergias')->nullable();
            $table->foreignId('tipo_contratacion_id')->nullable()->constrained('tipos_contratacion')->nullOnDelete();
            $table->decimal('salario_base', 10, 2);
            $table->foreignId('tipo_pago_id')->nullable()->constrained('tipos_pago')->nullOnDelete();
            $table->string('puesto', 100);
            $table->foreignId('sexo_id')->nullable()->constrained('sexos')->nullOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->string('foto_perfil', 255)->nullable();
            $table->string('estado', 20)->default('activo')->comment('activo, inactivo, suspendido');
            $table->timestamps();
            $table->softDeletes();

            $table->index('dpi');
            $table->index('estado');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal');
    }
};
