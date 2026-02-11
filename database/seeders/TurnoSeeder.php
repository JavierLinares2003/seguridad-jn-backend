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
                'nombre' => '8x16',
                'hora_inicio' => '08:00',
                'hora_fin' => '17:00',
                'horas_trabajo' => 8.00,
                'descripcion' => 'Turno diario de 8 horas (jornada normal: 8am-5pm)',
                'requiere_descanso' => false,
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
                'nombre' => '24x24',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 24.00,
                'descripcion' => 'Turno de 24 horas de trabajo por 24 horas de descanso (1 día trabaja, 1 día descansa)',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '48x32',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 48.00,
                'descripcion' => 'Turno de 48 horas de trabajo por 32 horas de descanso',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '48x48',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 48.00,
                'descripcion' => 'Turno de 48 horas de trabajo por 48 horas de descanso (2 días trabaja, 2 días descansa)',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '72x72',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 72.00,
                'descripcion' => 'Turno de 72 horas de trabajo por 72 horas de descanso (3 días trabaja, 3 días descansa)',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '96x96',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 96.00,
                'descripcion' => 'Turno de 96 horas de trabajo por 96 horas de descanso (4 días trabaja, 4 días descansa)',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => '120x120',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 120.00,
                'descripcion' => 'Turno de 120 horas de trabajo por 120 horas de descanso (5 días trabaja, 5 días descansa)',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => 'Administrativo',
                'hora_inicio' => '08:00',
                'hora_fin' => '17:00',
                'horas_trabajo' => 8.00,
                'descripcion' => 'Horario administrativo con hora de almuerzo',
                'requiere_descanso' => false,
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
