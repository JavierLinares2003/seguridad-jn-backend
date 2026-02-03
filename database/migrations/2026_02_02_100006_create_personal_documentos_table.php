<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('tipo_documento_personal_id')->constrained('tipos_documentos_personal')->cascadeOnDelete();
            $table->string('nombre_documento', 255);
            $table->text('descripcion')->nullable();
            $table->string('ruta_archivo', 500)->unique();
            $table->string('nombre_archivo_original', 255);
            $table->string('extension', 10);
            $table->integer('tamanio_kb');
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamp('fecha_subida')->useCurrent();
            $table->foreignId('subido_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_documento', 20)->default('vigente')->comment('vigente, por_vencer, vencido');
            $table->integer('dias_alerta_vencimiento')->default(30);
            $table->timestamps();
            $table->softDeletes();

            $table->index('personal_id');
            $table->index('fecha_vencimiento');
            $table->index('estado_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_documentos');
    }
};
