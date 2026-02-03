<?php

use App\Services\Operacion\AsistenciaService;
use App\Models\OperacionPersonalAsignado;
use App\Models\OperacionAsistencia;
use App\Models\Personal;
use App\Models\Catalogos\Turno;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    DB::beginTransaction();
    echo "--- Verifying Unassigned API Service (Self-contained) ---\n";

    // 1. Setup Data
    $turno = Turno::first();
    $personal = Personal::create([
        'nombres' => 'API Verify',
        'apellidos' => 'User',
        'dpi' => 'API' . rand(1000,9999), 
        'email' => 'api' . rand(1000,9999) . '@example.com',
        'telefono' => '12345678',
        'fecha_nacimiento' => '1990-01-01',
        'puesto' => 'Agente',
        'salario_base' => 3000,
        'altura' => 1.70,
        'peso' => 150
    ]);

    $assignment = OperacionPersonalAsignado::create([
        'personal_id' => $personal->id,
        'proyecto_id' => null,
        'configuracion_puesto_id' => null,
        'turno_id' => $turno->id,
        'fecha_inicio' => Carbon::today(),
        'estado_asignacion' => 'activa'
    ]);

    $asistencia = OperacionAsistencia::create([
        'personal_asignado_id' => $assignment->id,
        'fecha_asistencia' => Carbon::today(),
        'hora_entrada' => '08:00',
        'registrado_por_user_id' => 1
    ]);

    echo "Created Data: Personal {$personal->id}, Assignment {$assignment->id}, Attendance {$asistencia->id}\n";

    // 2. Test Service
    $service = app(AsistenciaService::class);
    
    echo "Querying service with project_id = 0...\n";
    $results = $service->getAsistenciaPorProyectoYFecha(0, Carbon::today());
    
    echo "Results Count: " . $results->count() . "\n";
    
    $found = false;
    foreach ($results as $item) {
        if ($item['asignacion']['id'] == $assignment->id) {
            echo "Found created assignment!\n";
            echo "Service returned Attendance ID: " . ($item['asistencia']['id'] ?? 'NULL') . "\n";
            if ($item['asistencia']['id'] == $asistencia->id) {
                $found = true;
            }
        }
    }

    if ($found) {
        echo "--- Verification Success ---\n";
    } else {
        echo "--- Verification Failed: Item not found in service response ---\n";
    }

    DB::rollBack();
    echo "Rolled back changes.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "--- Verification Failed ---\n";
    echo "Error: " . $e->getMessage() . "\n";
}
