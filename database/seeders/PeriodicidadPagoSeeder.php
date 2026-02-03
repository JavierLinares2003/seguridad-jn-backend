<?php

namespace Database\Seeders;

use App\Models\Catalogos\PeriodicidadPago;
use Illuminate\Database\Seeder;

class PeriodicidadPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $periodicidades = [
            ['nombre' => 'Mensual', 'dias' => 30],
            ['nombre' => 'Quincenal', 'dias' => 15],
        ];

        foreach ($periodicidades as $periodo) {
            PeriodicidadPago::firstOrCreate(
                ['nombre' => $periodo['nombre']],
                ['dias' => $periodo['dias'], 'activo' => true]
            );
        }
    }
}
