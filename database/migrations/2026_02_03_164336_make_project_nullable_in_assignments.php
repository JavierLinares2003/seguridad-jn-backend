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
        // 1. Drop the dependent view
        DB::statement('DROP VIEW IF EXISTS vw_alertas_cobertura_proyectos');

        // 2. Alter the table
        Schema::table('operaciones_personal_asignado', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable()->change();
            $table->foreignId('configuracion_puesto_id')->nullable()->change();
        });

        // 3. Recreate the view
        // Is identical to the original definition
        DB::statement("
            CREATE VIEW vw_alertas_cobertura_proyectos AS
            SELECT 
                p.id AS proyecto_id,
                p.nombre_proyecto,
                p.correlativo AS proyecto_correlativo,
                p.empresa_cliente AS cliente_nombre,
                pcp.id AS configuracion_puesto_id,
                pcp.nombre_puesto,
                pcp.cantidad_requerida,
                t.id AS turno_id,
                t.nombre AS turno_nombre,
                t.hora_inicio AS turno_hora_inicio,
                t.hora_fin AS turno_hora_fin,
                t.horas_trabajo AS turno_horas,
                
                COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                ) AS personal_asignado,
                
                pcp.cantidad_requerida - COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                ) AS deficit_personal,
                
                CASE
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) = 0 THEN 'sin_cobertura'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida THEN 'cobertura_parcial'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND opa.fecha_fin IS NOT NULL
                        AND opa.fecha_fin BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
                    ) > 0 THEN 'proximo_vencimiento'
                    ELSE 'cobertura_completa'
                END AS tipo_alerta,
                
                MIN(opa.fecha_fin) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND opa.fecha_fin IS NOT NULL
                    AND opa.fecha_fin >= CURRENT_DATE
                ) AS proxima_fecha_vencimiento,
                
                CASE
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) = 0 THEN 'critica'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida * 0.5 THEN 'alta'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida THEN 'media'
                    ELSE 'baja'
                END AS severidad

            FROM proyectos p
            INNER JOIN proyectos_configuracion_personal pcp ON p.id = pcp.proyecto_id
            INNER JOIN turnos t ON pcp.turno_id = t.id
            LEFT JOIN operaciones_personal_asignado opa ON pcp.id = opa.configuracion_puesto_id

            WHERE p.estado_proyecto = 'activo'
              AND pcp.estado = 'activo'

            GROUP BY 
                p.id, p.nombre_proyecto, p.correlativo, p.empresa_cliente,
                pcp.id, pcp.nombre_puesto, pcp.cantidad_requerida,
                t.id, t.nombre, t.hora_inicio, t.hora_fin, t.horas_trabajo

            HAVING 
                COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                ) < pcp.cantidad_requerida
                OR
                COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND opa.fecha_fin IS NOT NULL
                    AND opa.fecha_fin BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
                ) > 0

            ORDER BY 
                CASE 
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) = 0 THEN 1
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida * 0.5 THEN 2
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida THEN 3
                    ELSE 4
                END,
                p.nombre_proyecto, pcp.nombre_puesto
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop the dependent view
        DB::statement('DROP VIEW IF EXISTS vw_alertas_cobertura_proyectos');

        // 2. Revert the table
        Schema::table('operaciones_personal_asignado', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable(false)->change();
            $table->foreignId('configuracion_puesto_id')->nullable(false)->change();
        });

        // 3. Recreate the view
        DB::statement("
            CREATE VIEW vw_alertas_cobertura_proyectos AS
            SELECT 
                p.id AS proyecto_id,
                p.nombre_proyecto,
                p.correlativo AS proyecto_correlativo,
                p.empresa_cliente AS cliente_nombre,
                pcp.id AS configuracion_puesto_id,
                pcp.nombre_puesto,
                pcp.cantidad_requerida,
                t.id AS turno_id,
                t.nombre AS turno_nombre,
                t.hora_inicio AS turno_hora_inicio,
                t.hora_fin AS turno_hora_fin,
                t.horas_trabajo AS turno_horas,
                
                COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                ) AS personal_asignado,
                
                pcp.cantidad_requerida - COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                ) AS deficit_personal,
                
                CASE
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) = 0 THEN 'sin_cobertura'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida THEN 'cobertura_parcial'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND opa.fecha_fin IS NOT NULL
                        AND opa.fecha_fin BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
                    ) > 0 THEN 'proximo_vencimiento'
                    ELSE 'cobertura_completa'
                END AS tipo_alerta,
                
                MIN(opa.fecha_fin) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND opa.fecha_fin IS NOT NULL
                    AND opa.fecha_fin >= CURRENT_DATE
                ) AS proxima_fecha_vencimiento,
                
                CASE
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) = 0 THEN 'critica'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida * 0.5 THEN 'alta'
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida THEN 'media'
                    ELSE 'baja'
                END AS severidad

            FROM proyectos p
            INNER JOIN proyectos_configuracion_personal pcp ON p.id = pcp.proyecto_id
            INNER JOIN turnos t ON pcp.turno_id = t.id
            LEFT JOIN operaciones_personal_asignado opa ON pcp.id = opa.configuracion_puesto_id

            WHERE p.estado_proyecto = 'activo'
              AND pcp.estado = 'activo'

            GROUP BY 
                p.id, p.nombre_proyecto, p.correlativo, p.empresa_cliente,
                pcp.id, pcp.nombre_puesto, pcp.cantidad_requerida,
                t.id, t.nombre, t.hora_inicio, t.hora_fin, t.horas_trabajo

            HAVING 
                COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                ) < pcp.cantidad_requerida
                OR
                COUNT(DISTINCT opa.id) FILTER (
                    WHERE opa.estado_asignacion = 'activa'
                    AND opa.fecha_fin IS NOT NULL
                    AND opa.fecha_fin BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
                ) > 0

            ORDER BY 
                CASE 
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) = 0 THEN 1
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida * 0.5 THEN 2
                    WHEN COUNT(DISTINCT opa.id) FILTER (
                        WHERE opa.estado_asignacion = 'activa'
                        AND (opa.fecha_fin IS NULL OR opa.fecha_fin >= CURRENT_DATE)
                    ) < pcp.cantidad_requerida THEN 3
                    ELSE 4
                END,
                p.nombre_proyecto, pcp.nombre_puesto
        ");
    }
};
