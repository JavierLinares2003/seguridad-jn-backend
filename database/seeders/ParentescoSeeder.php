<?php

namespace Database\Seeders;

use App\Models\Catalogos\Parentesco;
use Illuminate\Database\Seeder;

class ParentescoSeeder extends Seeder
{
    public function run(): void
    {
        $parentescos = [
            'Padre',
            'Madre',
            'Esposo/a',
            'Hijo/a',
            'Hermano/a',
            'Abuelo/a',
            'Nieto/a',
            'Tío/a',
            'Sobrino/a',
            'Primo/a',
            'Suegro/a',
            'Yerno/Nuera',
            'Cuñado/a',
            'Padrino/Madrina',
            'Amigo/a',
            'Otro',
        ];

        foreach ($parentescos as $parentesco) {
            Parentesco::firstOrCreate(['nombre' => $parentesco]);
        }
    }
}
