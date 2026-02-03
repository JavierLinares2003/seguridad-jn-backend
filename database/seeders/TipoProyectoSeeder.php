<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoProyecto;
use Illuminate\Database\Seeder;

class TipoProyectoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Seguridad Física',
            'Seguridad Electrónica',
            'Vigilancia',
            'Custodia',
            'Escolta',
            'Monitoreo',
            'Seguridad Residencial',
            'Seguridad Industrial',
            'Seguridad Comercial',
            'Eventos',
        ];

        foreach ($tipos as $tipo) {
            TipoProyecto::firstOrCreate(['nombre' => $tipo]);
        }
    }
}
