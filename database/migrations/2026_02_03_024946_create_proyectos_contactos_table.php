<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proyectos_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('nombre_contacto', 200);
            $table->string('telefono', 15);
            $table->string('email', 150)->nullable();
            $table->string('puesto', 100);
            $table->boolean('es_contacto_principal')->default(false);
            $table->timestamps();
        });

        // Add partial unique index to ensure only one principal contact per project
        // We use raw SQL because standard Blueprint unique method doesn't support 'where' clauses easily in all drivers via fluent syntax, although newer Laravel versions do.
        // For robustness on Postgres:
        DB::statement('CREATE UNIQUE INDEX proyectos_contactos_principal_unique ON proyectos_contactos (proyecto_id) WHERE es_contacto_principal = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyectos_contactos');
    }
};
