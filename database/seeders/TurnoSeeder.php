<?php

namespace Database\Seeders;

use App\Models\Catalogos\Turno;
use Illuminate\Database\Seeder;

class TurnoSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = [
            [
                'nombre' => '8x8',
                'hora_inicio' => '06:00',
                'hora_fin' => '14:00',
                'horas_trabajo' => 8.00,
                'descripcion' => 'Turno de 8 horas de trabajo por 8 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '12x12',
                'hora_inicio' => '06:00',
                'hora_fin' => '18:00',
                'horas_trabajo' => 12.00,
                'descripcion' => 'Turno de 12 horas de trabajo por 12 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '12x24',
                'hora_inicio' => '06:00',
                'hora_fin' => '18:00',
                'horas_trabajo' => 12.00,
                'descripcion' => 'Turno de 12 horas de trabajo por 24 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '24x24',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 24.00,
                'descripcion' => 'Turno de 24 horas de trabajo por 24 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '24x72',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 24.00,
                'descripcion' => 'Turno de 24 horas de trabajo por 72 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '48x48',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 48.00,
                'descripcion' => 'Turno de 48 horas de trabajo por 48 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '48x72',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 48.00,
                'descripcion' => 'Turno de 48 horas de trabajo por 72 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '72x72',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 72.00,
                'descripcion' => 'Turno de 72 horas de trabajo por 72 horas de descanso',
                'requiere_descanso' => true,
            ],
        ];

        foreach ($turnos as $turno) {
            Turno::firstOrCreate(
                ['nombre' => $turno['nombre']],
                [
                    'hora_inicio' => $turno['hora_inicio'],
                    'hora_fin' => $turno['hora_fin'],
                    'horas_trabajo' => $turno['horas_trabajo'],
                    'descripcion' => $turno['descripcion'],
                    'requiere_descanso' => $turno['requiere_descanso'],
                ]
            );
        }
    }
}
