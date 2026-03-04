<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar el trigger anterior
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_actualizar_saldo_prestamo ON operaciones_transacciones');
        DB::unprepared('DROP FUNCTION IF EXISTS actualizar_saldo_prestamo()');

        // Crear función mejorada para actualizar saldo de préstamo
        DB::unprepared('
            CREATE OR REPLACE FUNCTION actualizar_saldo_prestamo()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Solo procesar si es una transacción de abono a préstamo y está aplicada
                IF NEW.tipo_transaccion = \'abono_prestamo\'
                   AND NEW.prestamo_id IS NOT NULL
                   AND NEW.estado_transaccion = \'aplicado\' THEN

                    -- Actualizar saldo del préstamo
                    UPDATE operaciones_prestamos
                    SET
                        saldo_pendiente = GREATEST(saldo_pendiente - NEW.monto, 0),
                        cuotas_pagadas = cuotas_pagadas + 1,
                        estado_prestamo = CASE
                            WHEN (saldo_pendiente - NEW.monto) <= 0.01 THEN \'pagado\'
                            ELSE estado_prestamo
                        END,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = NEW.prestamo_id
                    AND estado_prestamo = \'activo\';

                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Crear trigger actualizado
        DB::unprepared('
            CREATE TRIGGER trigger_actualizar_saldo_prestamo
            AFTER INSERT ON operaciones_transacciones
            FOR EACH ROW
            EXECUTE FUNCTION actualizar_saldo_prestamo();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar trigger y función actualizados
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_actualizar_saldo_prestamo ON operaciones_transacciones');
        DB::unprepared('DROP FUNCTION IF EXISTS actualizar_saldo_prestamo()');

        // Restaurar trigger original
        DB::unprepared('
            CREATE OR REPLACE FUNCTION actualizar_saldo_prestamo()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.tipo_transaccion = \'abono_prestamo\' AND NEW.prestamo_id IS NOT NULL THEN
                    UPDATE operaciones_prestamos
                    SET
                        saldo_pendiente = saldo_pendiente - NEW.monto,
                        cuotas_pagadas = cuotas_pagadas + 1,
                        estado_prestamo = CASE
                            WHEN (saldo_pendiente - NEW.monto) <= 0 THEN \'pagado\'
                            ELSE estado_prestamo
                        END,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = NEW.prestamo_id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER trigger_actualizar_saldo_prestamo
            AFTER INSERT ON operaciones_transacciones
            FOR EACH ROW
            EXECUTE FUNCTION actualizar_saldo_prestamo();
        ');
    }
};
