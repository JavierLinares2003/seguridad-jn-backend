<?php

use App\Models\Catalogos\TipoProyecto;
use App\Models\Proyecto;
use Illuminate\Support\Facades\DB;

try {
    echo "Iniciando verificación de Módulo de Proyectos...\n";

    // 1. Crear Tipo de Proyecto
    echo "1. Creando Tipo de Proyecto...\n";
    $tipo = TipoProyecto::firstOrCreate(
        ['prefijo_correlativo' => 'TEST'],
        [
            'nombre' => 'Proyecto de Prueba',
            'descripcion' => 'Tipo de proyecto para pruebas automatizadas',
            'activo' => true
        ]
    );
    echo "Tipo de Proyecto creado/encontrado: {$tipo->nombre} (ID: {$tipo->id})\n";

    // 2. Crear Proyecto
    echo "2. Creando Proyecto...\n";
    $proyecto = Proyecto::create([
        'tipo_proyecto_id' => $tipo->id,
        'nombre_proyecto' => 'Implementación de Seguridad',
        'descripcion' => 'Proyecto de prueba para verificar correlativo',
        'empresa_cliente' => 'Empresa Demo S.A.',
        'estado_proyecto' => 'planificacion',
        'fecha_inicio_estimada' => now(),
        'fecha_fin_estimada' => now()->addMonth(),
    ]);

    echo "Proyecto creado con ID: {$proyecto->id}\n";
    echo "Correlativo generado: {$proyecto->correlativo}\n";

    // Verificar formato del correlativo (PREFIJO-YEAR-001)
    $year = date('Y');
    $expectedPrefix = "TEST-{$year}-";
    
    if (str_starts_with($proyecto->correlativo, $expectedPrefix)) {
        echo "[OK] El correlativo tiene el formato correcto.\n";
    } else {
        echo "[ERROR] El correlativo NO tiene el formato correcto. Esperado: {$expectedPrefix}XXX, Actual: {$proyecto->correlativo}\n";
    }

    // 3. Crear segundo proyecto para verificar secuencia
    echo "3. Creando segundo proyecto para verificar secuencia...\n";
    $proyecto2 = Proyecto::create([
        'tipo_proyecto_id' => $tipo->id,
        'nombre_proyecto' => 'Mantenimiento Preventivo',
        'empresa_cliente' => 'Cliente B',
    ]);
    echo "Segundo proyecto correlativo: {$proyecto2->correlativo}\n";

    // 4. Verificar SoftDeletes
    echo "4. Verificando SoftDeletes...\n";
    $proyecto->delete();
    
    if ($proyecto->trashed()) {
        echo "[OK] El proyecto fue eliminado suavemente (SoftDeleted).\n";
    } else {
        echo "[ERROR] El proyecto NO fue eliminado correctamente.\n";
    }

    $conteo = Proyecto::where('id', $proyecto->id)->count();
    $conteoTotal = Proyecto::withTrashed()->where('id', $proyecto->id)->count();
    
    echo "Proyectos activos con ID {$proyecto->id}: {$conteo}\n";
    echo "Proyectos totales (incluyendo eliminados) con ID {$proyecto->id}: {$conteoTotal}\n";

    echo "\nVerificación completada exitosamente.\n";

} catch (\Exception $e) {
    echo "\n[ERROR] Excepción durante la verificación: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
