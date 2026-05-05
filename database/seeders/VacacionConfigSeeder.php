<?php

namespace Database\Seeders;

use App\Models\Catalogos\Departamento;
use App\Models\VacacionConfig;
use Illuminate\Database\Seeder;

class VacacionConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Configuración por defecto para todos los departamentos
        VacacionConfig::updateOrCreate(
            ['departamento_id' => null],
            ['dias_por_anio' => 8, 'descripcion' => 'Días de vacaciones por defecto']
        );

        // El departamento de Seguridad tiene 4 días por año
        $seguridad = Departamento::where('nombre', 'Seguridad')->first();
        if ($seguridad) {
            VacacionConfig::updateOrCreate(
                ['departamento_id' => $seguridad->id],
                ['dias_por_anio' => 4, 'descripcion' => 'Días de vacaciones para personal de Seguridad']
            );
        }
    }
}
