<?php

namespace Database\Seeders;

use App\Models\Catalogos\Sexo;
use Illuminate\Database\Seeder;

class SexoSeeder extends Seeder
{
    public function run(): void
    {
        $sexos = ['Masculino', 'Femenino', 'Otro', 'Ambos'];

        foreach ($sexos as $sexo) {
            Sexo::firstOrCreate(['nombre' => $sexo]);
        }
    }
}
