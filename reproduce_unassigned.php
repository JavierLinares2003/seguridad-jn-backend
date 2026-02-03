<?php

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

    echo "--- Testing Unassigned Assignment ---\n";

    // 1. Get dependencies
    $turno = Turno::first();
    if (!$turno) die("No turno found\n");
    
    // Create temp personal
    $personal = Personal::create([
        'nombres' => 'Test',
        'apellidos' => 'Unassigned',
        'dpi' => 'TEST' . rand(1000,9999), 
        'email' => 'test' . rand(1000,9999) . '@example.com',
        'telefono' => '12345678',
        'fecha_nacimiento' => '1990-01-01',
        'puesto' => 'Agente',
        'salario_base' => 3000,
        'altura' => 1.70,
        'peso' => 150
    ]);
    
    echo "Created Temp Personal: {$personal->nombres} (ID: {$personal->id})\n";
    echo "Turno: {$turno->nombre} (ID: {$turno->id})\n";

    // 2. Create Assignment without Project
    echo "Creating Assignment (No Project)...\n";
    $assignment = OperacionPersonalAsignado::create([
        'personal_id' => $personal->id,
        'proyecto_id' => null,
        'configuracion_puesto_id' => null,
        'turno_id' => $turno->id,
        'fecha_inicio' => Carbon::today(),
        'estado_asignacion' => 'activa',
        'notas' => 'Test unassigned assignment'
    ]);
    
    echo "Assignment Created: ID {$assignment->id}\n";

    // 3. Register Attendance
    echo "Registering Attendance...\n";
    $asistencia = OperacionAsistencia::create([
        'personal_asignado_id' => $assignment->id,
        'fecha_asistencia' => Carbon::today(),
        'hora_entrada' => '08:00',
        'registrado_por_user_id' => 1 // Assuming user 1 exists
    ]);

    echo "Attendance Registered: ID {$asistencia->id}\n";

    DB::rollBack(); // Always rollback test
    echo "--- Test Success (Rolled back) ---\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "--- Test Failed ---\n";
    echo "Error: " . $e->getMessage() . "\n";
    // Check for trigger error codes
    if (strpos($e->getMessage(), 'P00')) {
        echo "Trigger Error Code Detected\n";
    }
}
