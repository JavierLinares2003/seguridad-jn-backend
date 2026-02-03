<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoContratacion;
use Illuminate\Database\Seeder;

class TipoContratacionSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Indefinido',
            'Plazo Fijo',
            'Por Proyecto',
            'Temporal',
            'Por Servicios Profesionales',
        ];

        foreach ($tipos as $tipo) {
            TipoContratacion::firstOrCreate(['nombre' => $tipo]);
        }
    }
}
