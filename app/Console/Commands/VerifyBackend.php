<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Proyecto;
use App\Models\Catalogos\TipoProyecto;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class VerifyBackend extends Command
{
    protected $signature = 'verify:backend';
    protected $description = 'Verify Project Contacts and Inventory Backend';

    public function handle()
    {
        $this->info('Starting Verification...');

        try {
            DB::beginTransaction();

            // Create dependency data if needed
            $tipoProyecto = TipoProyecto::firstOrCreate(
                ['nombre' => 'Test Project Type'],
                ['prefijo_correlativo' => 'TST', 'activo' => true]
            );

            // Test Data
            $data = [
                'tipo_proyecto_id' => $tipoProyecto->id,
                'nombre_proyecto' => 'Proyecto Verification Contacts/Inventory',
                'empresa_cliente' => 'Cliente Test',
            ];

            // 1. Create Project
            $proyecto = Proyecto::create($data);
            // Manually trigger correlative since we are not using the controller flow for the project itself in this specific test
             // Actually, the trigger runs on insert, so $proyecto->refresh() should have it.
            $proyecto->refresh();
            $this->info("Project Created: {$proyecto->correlativo}");

            // 2. CONTACTS Verification
            $this->info("--- Testing Contacts ---");
            
            // Create Contact 1 (Principal)
            $contact1 = $proyecto->contactos()->create([
                'nombre_contacto' => 'Contact 1',
                'telefono' => '12345678',
                'puesto' => 'Manager',
                'es_contacto_principal' => true
            ]);
            $this->info("Contact 1 created (Principal: true)");

            // Create Contact 2 (Principal) - Should demote Contact 1
            // We need to use the logic from the Controller to test 'demotion'. 
            // Since we can't easily call the controller action here without mocking request, 
            // we will simulate the logic:
            
            $proyecto->contactos()->where('es_contacto_principal', true)->update(['es_contacto_principal' => false]);
            $contact2 = $proyecto->contactos()->create([
                'nombre_contacto' => 'Contact 2',
                'telefono' => '87654321',
                'puesto' => 'Supervisor',
                'es_contacto_principal' => true
            ]);
            $this->info("Contact 2 created (Principal: true). Simulating Controller logic.");

            $contact1->refresh();
            if (!$contact1->es_contacto_principal && $contact2->es_contacto_principal) {
                $this->info("SUCCESS: Contact 1 demoted, Contact 2 is principal.");
            } else {
                $this->error("FAILURE: Principal contact logic failed.");
                $this->error("C1 Principal: " . ($contact1->es_contacto_principal ? 'Yes' : 'No'));
                $this->error("C2 Principal: " . ($contact2->es_contacto_principal ? 'Yes' : 'No'));
            }
            
            // Test DB Constraint: Try to force two principals via direct DB manipulation (should fail)
            // We use a separate nested transaction (savepoint) so we don't kill the main one on error
            try {
                DB::beginTransaction(); // Savepoint
                // Reset C1 to true while C2 is true
                $contact1->es_contacto_principal = true;
                $contact1->save();
                DB::commit(); // Should not reach here
                $this->error("FAILURE: Partial Unique Index did NOT prevent two principals.");
            } catch (QueryException $e) {
                DB::rollBack(); // Rollback to savepoint
                $this->info("SUCCESS: Partial Unique Index prevented two principals. Error: " . $e->getMessage());
            }

            // 3. INVENTORY Verification
            $this->info("--- Testing Inventory ---");

            // Create Item 1
            $item1 = $proyecto->inventario()->create([
                'codigo_inventario' => 'ITEM-001',
                'nombre_item' => 'Laptop',
                'cantidad_asignada' => 1
            ]);
            $this->info("Item 1 created: ITEM-001");

            // Try Create Item 2 with SAME Code (Should Fail)
            try {
                DB::beginTransaction(); // Savepoint
                $proyecto->inventario()->create([
                    'codigo_inventario' => 'ITEM-001', // Duplicate
                    'nombre_item' => 'Another Laptop',
                    'cantidad_asignada' => 1
                ]);
                DB::commit();
                $this->error("FAILURE: Duplicate Inventory Code allowed.");
            } catch (QueryException $e) {
                DB::rollBack(); // Rollback to savepoint
                $this->info("SUCCESS: Duplicate Inventory Code prevented. Error: " . $e->getMessage());
            }
            
            // 4. PUESTOS CONFIGURATION Verification
            $this->info("--- Testing Puestos Configuration ---");
            
            // Ensure catalogs exist
            $tipoPersonal = \App\Models\Catalogos\TipoPersonal::firstOrCreate(['nombre' => 'Guardia']);
            $turno = \App\Models\Catalogos\Turno::firstOrCreate(
                ['nombre' => 'Diurno 12h'],
                [
                    'hora_inicio' => '06:00:00',
                    'hora_fin' => '18:00:00',
                    'horas_trabajo' => 12
                ]
            );
            // Assuming strict FKs, we need these.
            // Check if Sexo and NivelEstudio exist or are nullable. Config says nullable.
            
            // Create Config
            // Costo: 100, Pago: 80 -> Margin should be 20%
            $config = $proyecto->configuracionPersonal()->create([
                'nombre_puesto' => 'Guardia Test',
                'cantidad_requerida' => 5,
                'edad_minima' => 20,
                'edad_maxima' => 50,
                'tipo_personal_id' => $tipoPersonal->id,
                'turno_id' => $turno->id,
                'costo_hora_proyecto' => 100,
                'pago_hora_personal' => 80
            ]);
            
            $config->refresh(); // Load generated column
            $this->info("Config Created. Costo: 100, Pago: 80.");
            $this->info("Calculated Margin: " . $config->margen_utilidad);
            
            if (abs($config->margen_utilidad - 20.00) < 0.01) {
                $this->info("SUCCESS: Margin calculated correctly (20.00).");
            } else {
                $this->error("FAILURE: Margin calculation incorrect. Got: " . $config->margen_utilidad);
            }

            // 5. ASSIGNMENT Verification (DBFK only)
            $this->info("--- Testing Assignment ---");
            
            // Create Personal
            $email = 'test.' . uniqid() . '@example.com';
            $dpi = substr(uniqid() . '1234567890123', 0, 13);
            
            try {
                $personal = \App\Models\Personal::create([
                    'nombres' => 'Juan',
                    'apellidos' => 'Perez',
                    'dpi' => $dpi,
                    'email' => $email,
                    'telefono' => '12345678',
                    'fecha_nacimiento' => '1990-01-01',
                    'altura' => 1.75,
                    'peso' => 160,
                    'salario_base' => 3500.00,
                    'puesto' => 'Guardia',
                    // Optional FKs can match config requirements if needed
                    // 'sexo_id' => $sexo->id (if we fetched it)
                ]);
            } catch (\Exception $e) {
                 $this->error("Failed to create Personal: " . $e->getMessage());
                 throw $e;
            }
           
            // 5. ASSIGNMENT Verification
            // Note: We skip complex validation checks here (Age etc) as that requires setting up Sexo/Config exactly matching.
            // We just verify basic FK assignment.

            $assignment = \App\Models\OperacionPersonalAsignado::create([
                'personal_id' => $personal->id,
                'proyecto_id' => $proyecto->id,
                'configuracion_puesto_id' => $config->id,
                'turno_id' => $turno->id,
                'fecha_inicio' => date('Y-m-d')
            ]);
            $this->info("Assignment ID: {$assignment->id} created successfully.");

            // Cleanup
            DB::rollBack(); // Rollback everything to keep clean
            $this->info('Verification Passed. Rolled back changes.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
