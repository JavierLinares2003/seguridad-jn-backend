<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar triggers e función existentes
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_actualizar_saldo_prestamo ON operaciones_transacciones');
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_actualizar_saldo_prestamo_update ON operaciones_transacciones');
        DB::unprepared('DROP FUNCTION IF EXISTS actualizar_saldo_prestamo()');

        // Función que maneja tanto INSERT como UPDATE
        // En INSERT: solo procesa si ya viene como 'aplicado'
        // En UPDATE: solo procesa cuando el estado CAMBIA de otro valor a 'aplicado'
        DB::unprepared("
            CREATE OR REPLACE FUNCTION actualizar_saldo_prestamo()
            RETURNS TRIGGER AS \$\$
            BEGIN
                -- Solo procesar abonos a préstamos vinculados a un préstamo
                IF NEW.tipo_transaccion = 'abono_prestamo' AND NEW.prestamo_id IS NOT NULL THEN

                    -- En UPDATE: solo actuar cuando el estado cambia a 'aplicado'
                    IF TG_OP = 'UPDATE' THEN
                        IF OLD.estado_transaccion = 'aplicado' OR NEW.estado_transaccion <> 'aplicado' THEN
                            RETURN NEW;
                        END IF;
                    END IF;

                    -- En INSERT: solo actuar si ya viene como 'aplicado'
                    IF TG_OP = 'INSERT' AND NEW.estado_transaccion <> 'aplicado' THEN
                        RETURN NEW;
                    END IF;

                    -- Actualizar saldo del préstamo
                    UPDATE operaciones_prestamos
                    SET
                        saldo_pendiente = GREATEST(saldo_pendiente - NEW.monto, 0),
                        cuotas_pagadas  = cuotas_pagadas + 1,
                        estado_prestamo = CASE
                            WHEN (saldo_pendiente - NEW.monto) <= 0.01 THEN 'pagado'
                            ELSE estado_prestamo
                        END,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = NEW.prestamo_id
                      AND estado_prestamo = 'activo';

                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Trigger para INSERT (abonos manuales aplicados directamente)
        DB::unprepared('
            CREATE TRIGGER trigger_actualizar_saldo_prestamo
            AFTER INSERT ON operaciones_transacciones
            FOR EACH ROW
            EXECUTE FUNCTION actualizar_saldo_prestamo();
        ');

        // Trigger para UPDATE (cuotas de planilla que pasan de pendiente → aplicado)
        DB::unprepared('
            CREATE TRIGGER trigger_actualizar_saldo_prestamo_update
            AFTER UPDATE OF estado_transaccion ON operaciones_transacciones
            FOR EACH ROW
            EXECUTE FUNCTION actualizar_saldo_prestamo();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_actualizar_saldo_prestamo ON operaciones_transacciones');
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_actualizar_saldo_prestamo_update ON operaciones_transacciones');
        DB::unprepared('DROP FUNCTION IF EXISTS actualizar_saldo_prestamo()');
    }
};
