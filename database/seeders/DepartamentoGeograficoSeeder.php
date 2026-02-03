<?php

namespace Database\Seeders;

use App\Models\Catalogos\DepartamentoGeografico;
use Illuminate\Database\Seeder;

class DepartamentoGeograficoSeeder extends Seeder
{
    public function run(): void
    {
        $departamentos = [
            ['codigo' => '01', 'nombre' => 'Guatemala'],
            ['codigo' => '02', 'nombre' => 'El Progreso'],
            ['codigo' => '03', 'nombre' => 'Sacatepéquez'],
            ['codigo' => '04', 'nombre' => 'Chimaltenango'],
            ['codigo' => '05', 'nombre' => 'Escuintla'],
            ['codigo' => '06', 'nombre' => 'Santa Rosa'],
            ['codigo' => '07', 'nombre' => 'Sololá'],
            ['codigo' => '08', 'nombre' => 'Totonicapán'],
            ['codigo' => '09', 'nombre' => 'Quetzaltenango'],
            ['codigo' => '10', 'nombre' => 'Suchitepéquez'],
            ['codigo' => '11', 'nombre' => 'Retalhuleu'],
            ['codigo' => '12', 'nombre' => 'San Marcos'],
            ['codigo' => '13', 'nombre' => 'Huehuetenango'],
            ['codigo' => '14', 'nombre' => 'Quiché'],
            ['codigo' => '15', 'nombre' => 'Baja Verapaz'],
            ['codigo' => '16', 'nombre' => 'Alta Verapaz'],
            ['codigo' => '17', 'nombre' => 'Petén'],
            ['codigo' => '18', 'nombre' => 'Izabal'],
            ['codigo' => '19', 'nombre' => 'Zacapa'],
            ['codigo' => '20', 'nombre' => 'Chiquimula'],
            ['codigo' => '21', 'nombre' => 'Jalapa'],
            ['codigo' => '22', 'nombre' => 'Jutiapa'],
        ];

        foreach ($departamentos as $depto) {
            DepartamentoGeografico::firstOrCreate(
                ['codigo' => $depto['codigo']],
                ['nombre' => $depto['nombre']]
            );
        }
    }
}
