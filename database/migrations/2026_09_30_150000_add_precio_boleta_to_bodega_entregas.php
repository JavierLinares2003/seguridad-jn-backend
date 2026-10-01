<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bodega_entregas', 'precio_boleta')) {
            Schema::table('bodega_entregas', function (Blueprint $table) {
                $table->decimal('precio_boleta', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bodega_entregas', 'precio_boleta')) {
            Schema::table('bodega_entregas', function (Blueprint $table) {
                $table->dropColumn('precio_boleta');
            });
        }
    }
};
