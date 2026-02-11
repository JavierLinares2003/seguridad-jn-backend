<?php

namespace Database\Seeders;

use App\Models\Catalogos\TipoPersonal;
use Illuminate\Database\Seeder;

class TipoPersonalSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Rondín',
            'Jefe de grupo',
            'Custodio',
            'Garitero',
            'Escolta',
            'Piloto',
            'Patrullero',
            'Patrulla reforzada',
            'Agente',
            'Anfitrión',
            'Técnico',
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
