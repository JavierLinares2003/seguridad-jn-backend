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
        // Create function to update loan balance
        DB::unprepared('
            CREATE OR REPLACE FUNCTION actualizar_saldo_prestamo()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Only process if it\'s a loan payment transaction
                IF NEW.tipo_transaccion = \'abono_prestamo\' AND NEW.prestamo_id IS NOT NULL THEN
                    -- Update loan balance and paid installments
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
        
        // Create trigger
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
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_actualizar_saldo_prestamo ON operaciones_transacciones');
        DB::unprepared('DROP FUNCTION IF EXISTS actualizar_saldo_prestamo()');
    }
};
