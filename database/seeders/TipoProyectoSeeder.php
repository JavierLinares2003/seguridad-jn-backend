<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoProyecto;
use Illuminate\Database\Seeder;

class TipoProyectoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'Servicio', 'prefijo_correlativo' => 'SRV'],
            ['nombre' => 'Proyecto', 'prefijo_correlativo' => 'PRY'],
        ];

        foreach ($tipos as $tipo) {
            TipoProyecto::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                ['prefijo_correlativo' => $tipo['prefijo_correlativo']]
            );
        }
    }
}
