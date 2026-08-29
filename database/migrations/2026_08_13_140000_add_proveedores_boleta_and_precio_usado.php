<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodega_proveedores', function (Blueprint $table) {
            $table->string('insumo', 150)->nullable()->after('nombre');
            $table->string('telefono', 40)->nullable()->after('insumo');
            $table->string('numero_cuenta', 80)->nullable()->after('telefono');
            $table->string('banco', 80)->nullable()->after('numero_cuenta');
        });

        Schema::table('bodega_productos', function (Blueprint $table) {
            $table->decimal('precio_usado', 12, 2)->nullable()->after('precio_venta');
        });

        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->string('numero_boleta', 20)->nullable()->unique()->after('id');
        });

        DB::unprepared("
            CREATE OR REPLACE FUNCTION generar_numero_boleta_bodega()
            RETURNS TRIGGER AS $$
            DECLARE
                secuencia INTEGER;
                nuevo_numero VARCHAR;
            BEGIN
                IF NEW.numero_boleta IS NOT NULL AND BTRIM(NEW.numero_boleta) <> '' THEN
                    RETURN NEW;
                END IF;

                PERFORM pg_advisory_xact_lock(hashtext('bodega_entrega_boleta'));

                SELECT COALESCE(MAX(
                    NULLIF(regexp_replace(numero_boleta, '[^0-9]', '', 'g'), '')::INTEGER
                ), 0) + 1
                INTO secuencia
                FROM bodega_entregas;

                LOOP
                    nuevo_numero := LPAD(secuencia::TEXT, 7, '0');
                    EXIT WHEN NOT EXISTS (
                        SELECT 1 FROM bodega_entregas WHERE numero_boleta = nuevo_numero
                    );
                    secuencia := secuencia + 1;
                END LOOP;

                NEW.numero_boleta := nuevo_numero;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trigger_generar_numero_boleta_bodega
            BEFORE INSERT ON bodega_entregas
            FOR EACH ROW
            EXECUTE FUNCTION generar_numero_boleta_bodega();
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_generar_numero_boleta_bodega ON bodega_entregas');
        DB::unprepared('DROP FUNCTION IF EXISTS generar_numero_boleta_bodega()');

        Schema::table('bodega_entregas', function (Blueprint $table) {
            $table->dropColumn('numero_boleta');
        });
        Schema::table('bodega_productos', function (Blueprint $table) {
            $table->dropColumn('precio_usado');
        });
        Schema::table('bodega_proveedores', function (Blueprint $table) {
            $table->dropColumn(['insumo', 'telefono', 'numero_cuenta', 'banco']);
        });
    }
};
