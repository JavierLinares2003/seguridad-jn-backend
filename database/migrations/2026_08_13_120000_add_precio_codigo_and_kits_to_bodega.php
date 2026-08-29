<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodega_categorias', function (Blueprint $table) {
            $table->string('prefijo_correlativo', 10)->nullable()->after('codigo');
        });

        DB::table('bodega_categorias')->where('codigo', 'uniforme_agentes')->update(['prefijo_correlativo' => 'UNI']);
        DB::table('bodega_categorias')->where('codigo', 'uniforme_admin')->update(['prefijo_correlativo' => 'ADM']);
        DB::table('bodega_categorias')->where('codigo', 'sueter_militar')->update(['prefijo_correlativo' => 'SUE']);
        DB::table('bodega_categorias')->where('codigo', 'libreria')->update(['prefijo_correlativo' => 'LIB']);
        DB::table('bodega_categorias')->where('codigo', 'accesorios_uniforme')->update(['prefijo_correlativo' => 'ACC']);
        DB::table('bodega_categorias')->where('codigo', 'accesorios_puesto')->update(['prefijo_correlativo' => 'PUE']);
        DB::table('bodega_categorias')->where('codigo', 'equipo_lluvia')->update(['prefijo_correlativo' => 'LLU']);
        DB::table('bodega_categorias')->where('codigo', 'limpieza')->update(['prefijo_correlativo' => 'LIM']);
        DB::table('bodega_categorias')->where('codigo', 'mecanico')->update(['prefijo_correlativo' => 'MEC']);
        DB::table('bodega_categorias')->whereNull('prefijo_correlativo')->update(['prefijo_correlativo' => 'BOD']);

        Schema::table('bodega_productos', function (Blueprint $table) {
            $table->string('codigo', 40)->nullable()->unique()->after('categoria_id');
            $table->decimal('precio_venta', 12, 2)->nullable()->after('unidad');
        });

        Schema::create('bodega_kits', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 40)->nullable()->unique();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('bodega_kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->constrained('bodega_kits')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('bodega_productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad')->default(1);
            $table->timestamps();
            $table->unique(['kit_id', 'producto_id']);
        });

        DB::unprepared("
            CREATE OR REPLACE FUNCTION generar_correlativo_bodega_producto()
            RETURNS TRIGGER AS $$
            DECLARE
                prefijo VARCHAR;
                secuencia INTEGER;
                nuevo_codigo VARCHAR;
                secuencia_texto VARCHAR;
            BEGIN
                IF NEW.codigo IS NOT NULL AND BTRIM(NEW.codigo) <> '' THEN
                    RETURN NEW;
                END IF;

                SELECT COALESCE(prefijo_correlativo, 'BOD') INTO prefijo
                FROM bodega_categorias
                WHERE id = NEW.categoria_id;

                PERFORM pg_advisory_xact_lock(
                    hashtext('bodega_producto_codigo_' || COALESCE(prefijo, 'BOD'))
                );

                SELECT COALESCE(MAX(
                    NULLIF(regexp_replace(SPLIT_PART(codigo, '-', 2), '[^0-9]', '', 'g'), '')::INTEGER
                ), 0) + 1
                INTO secuencia
                FROM bodega_productos
                WHERE codigo LIKE (prefijo || '-%');

                LOOP
                    secuencia_texto := LPAD(secuencia::TEXT, 4, '0');
                    nuevo_codigo := prefijo || '-' || secuencia_texto;
                    EXIT WHEN NOT EXISTS (
                        SELECT 1 FROM bodega_productos WHERE codigo = nuevo_codigo
                    );
                    secuencia := secuencia + 1;
                END LOOP;

                NEW.codigo := nuevo_codigo;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trigger_generar_correlativo_bodega_producto
            BEFORE INSERT ON bodega_productos
            FOR EACH ROW
            EXECUTE FUNCTION generar_correlativo_bodega_producto();
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_generar_correlativo_bodega_producto ON bodega_productos');
        DB::unprepared('DROP FUNCTION IF EXISTS generar_correlativo_bodega_producto()');
        Schema::dropIfExists('bodega_kit_items');
        Schema::dropIfExists('bodega_kits');
        Schema::table('bodega_productos', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'precio_venta']);
        });
        Schema::table('bodega_categorias', function (Blueprint $table) {
            $table->dropColumn('prefijo_correlativo');
        });
    }
};
