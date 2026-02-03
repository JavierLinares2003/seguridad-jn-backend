<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoPersonal;
use Illuminate\Database\Seeder;

class TipoPersonalSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Operativo',
            'Administrativo',
            'Supervisor',
            'Gerencial',
            'Directivo',
        ];

        foreach ($tipos as $tipo) {
            TipoPersonal::firstOrCreate(['nombre' => $tipo]);
        }
    }
}
