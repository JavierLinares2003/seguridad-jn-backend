<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoSangre;
use Illuminate\Database\Seeder;

class TipoSangreSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

        foreach ($tipos as $tipo) {
            TipoSangre::firstOrCreate(['nombre' => $tipo]);
        }
    }
}
