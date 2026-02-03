<?php

namespace Database\Seeders;

use App\Models\Catalogos\EstadoCivil;
use Illuminate\Database\Seeder;

class EstadoCivilSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            'Soltero/a',
            'Casado/a',
            'Divorciado/a',
            'Viudo/a',
            'Unión de Hecho',
        ];

        foreach ($estados as $estado) {
            EstadoCivil::firstOrCreate(['nombre' => $estado]);
        }
    }
}
