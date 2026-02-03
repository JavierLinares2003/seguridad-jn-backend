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
                'nombre' => 'Diurno 8 horas',
                'hora_inicio' => '06:00',
                'hora_fin' => '14:00',
                'horas_trabajo' => 8.00,
                'descripcion' => 'Turno diurno estándar de 8 horas',
                'requiere_descanso' => false,
            ],
            [
                'nombre' => 'Vespertino 8 horas',
                'hora_inicio' => '14:00',
                'hora_fin' => '22:00',
                'horas_trabajo' => 8.00,
                'descripcion' => 'Turno vespertino estándar de 8 horas',
                'requiere_descanso' => false,
            ],
            [
                'nombre' => 'Nocturno 8 horas',
                'hora_inicio' => '22:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 8.00,
                'descripcion' => 'Turno nocturno estándar de 8 horas',
                'requiere_descanso' => false,
            ],
            [
                'nombre' => 'Turno 12 horas Día',
                'hora_inicio' => '06:00',
                'hora_fin' => '18:00',
                'horas_trabajo' => 12.00,
                'descripcion' => 'Turno de 12 horas diurno',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => 'Turno 12 horas Noche',
                'hora_inicio' => '18:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 12.00,
                'descripcion' => 'Turno de 12 horas nocturno',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => 'Turno 24 horas',
                'hora_inicio' => '06:00',
                'hora_fin' => '06:00',
                'horas_trabajo' => 24.00,
                'descripcion' => 'Turno completo de 24 horas',
                'requiere_descanso' => true,
            ],
            [
                'nombre' => 'Administrativo',
                'hora_inicio' => '08:00',
                'hora_fin' => '17:00',
                'horas_trabajo' => 8.00,
                'descripcion' => 'Horario administrativo con hora de almuerzo',
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
