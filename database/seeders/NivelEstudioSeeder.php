<?php

namespace Database\Seeders;

use App\Models\Catalogos\NivelEstudio;
use Illuminate\Database\Seeder;

class NivelEstudioSeeder extends Seeder
{
    public function run(): void
    {
        $niveles = [
            ['nombre' => 'Sin estudios', 'orden' => 1],
            ['nombre' => 'Primaria Incompleta', 'orden' => 2],
            ['nombre' => 'Primaria Completa', 'orden' => 3],
            ['nombre' => 'Básicos Incompletos', 'orden' => 4],
            ['nombre' => 'Básicos Completos', 'orden' => 5],
            ['nombre' => 'Diversificado Incompleto', 'orden' => 6],
            ['nombre' => 'Diversificado Completo', 'orden' => 7],
            ['nombre' => 'Técnico Universitario', 'orden' => 8],
            ['nombre' => 'Universidad Incompleta', 'orden' => 9],
            ['nombre' => 'Universidad Completa', 'orden' => 10],
            ['nombre' => 'Maestría', 'orden' => 11],
            ['nombre' => 'Doctorado', 'orden' => 12],
        ];

        foreach ($niveles as $nivel) {
            NivelEstudio::firstOrCreate(
                ['nombre' => $nivel['nombre']],
                ['orden' => $nivel['orden']]
            );
        }
    }
}
