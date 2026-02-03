<?php

namespace Database\Seeders;

use App\Models\Catalogos\DepartamentoGeografico;
use App\Models\Catalogos\Municipio;
use Illuminate\Database\Seeder;

class MunicipioSeeder extends Seeder
{
    public function run(): void
    {
        $municipios = [
            // Guatemala (01)
            '01' => [
                ['codigo' => '0101', 'nombre' => 'Guatemala'],
                ['codigo' => '0102', 'nombre' => 'Santa Catarina Pinula'],
                ['codigo' => '0103', 'nombre' => 'San José Pinula'],
                ['codigo' => '0104', 'nombre' => 'San José del Golfo'],
                ['codigo' => '0105', 'nombre' => 'Palencia'],
                ['codigo' => '0106', 'nombre' => 'Chinautla'],
                ['codigo' => '0107', 'nombre' => 'San Pedro Ayampuc'],
                ['codigo' => '0108', 'nombre' => 'Mixco'],
                ['codigo' => '0109', 'nombre' => 'San Pedro Sacatepéquez'],
                ['codigo' => '0110', 'nombre' => 'San Juan Sacatepéquez'],
                ['codigo' => '0111', 'nombre' => 'San Raymundo'],
                ['codigo' => '0112', 'nombre' => 'Chuarrancho'],
                ['codigo' => '0113', 'nombre' => 'Fraijanes'],
                ['codigo' => '0114', 'nombre' => 'Amatitlán'],
                ['codigo' => '0115', 'nombre' => 'Villa Nueva'],
                ['codigo' => '0116', 'nombre' => 'Villa Canales'],
                ['codigo' => '0117', 'nombre' => 'San Miguel Petapa'],
            ],
            // Sacatepéquez (03)
            '03' => [
                ['codigo' => '0301', 'nombre' => 'Antigua Guatemala'],
                ['codigo' => '0302', 'nombre' => 'Jocotenango'],
                ['codigo' => '0303', 'nombre' => 'Pastores'],
                ['codigo' => '0304', 'nombre' => 'Sumpango'],
                ['codigo' => '0305', 'nombre' => 'Santo Domingo Xenacoj'],
                ['codigo' => '0306', 'nombre' => 'Santiago Sacatepéquez'],
                ['codigo' => '0307', 'nombre' => 'San Bartolomé Milpas Altas'],
                ['codigo' => '0308', 'nombre' => 'San Lucas Sacatepéquez'],
                ['codigo' => '0309', 'nombre' => 'Santa Lucía Milpas Altas'],
                ['codigo' => '0310', 'nombre' => 'Magdalena Milpas Altas'],
                ['codigo' => '0311', 'nombre' => 'Santa María de Jesús'],
                ['codigo' => '0312', 'nombre' => 'Ciudad Vieja'],
                ['codigo' => '0313', 'nombre' => 'San Miguel Dueñas'],
                ['codigo' => '0314', 'nombre' => 'Alotenango'],
                ['codigo' => '0315', 'nombre' => 'San Antonio Aguas Calientes'],
                ['codigo' => '0316', 'nombre' => 'Santa Catarina Barahona'],
            ],
            // Escuintla (05)
            '05' => [
                ['codigo' => '0501', 'nombre' => 'Escuintla'],
                ['codigo' => '0502', 'nombre' => 'Santa Lucía Cotzumalguapa'],
                ['codigo' => '0503', 'nombre' => 'La Democracia'],
                ['codigo' => '0504', 'nombre' => 'Siquinalá'],
                ['codigo' => '0505', 'nombre' => 'Masagua'],
                ['codigo' => '0506', 'nombre' => 'Tiquisate'],
                ['codigo' => '0507', 'nombre' => 'La Gomera'],
                ['codigo' => '0508', 'nombre' => 'Guanagazapa'],
                ['codigo' => '0509', 'nombre' => 'San José'],
                ['codigo' => '0510', 'nombre' => 'Iztapa'],
                ['codigo' => '0511', 'nombre' => 'Palín'],
                ['codigo' => '0512', 'nombre' => 'San Vicente Pacaya'],
                ['codigo' => '0513', 'nombre' => 'Nueva Concepción'],
            ],
            // Quetzaltenango (09)
            '09' => [
                ['codigo' => '0901', 'nombre' => 'Quetzaltenango'],
                ['codigo' => '0902', 'nombre' => 'Salcajá'],
                ['codigo' => '0903', 'nombre' => 'Olintepeque'],
                ['codigo' => '0904', 'nombre' => 'San Carlos Sija'],
                ['codigo' => '0905', 'nombre' => 'Sibilia'],
                ['codigo' => '0906', 'nombre' => 'Cabricán'],
                ['codigo' => '0907', 'nombre' => 'Cajolá'],
                ['codigo' => '0908', 'nombre' => 'San Miguel Sigüilá'],
                ['codigo' => '0909', 'nombre' => 'Ostuncalco'],
                ['codigo' => '0910', 'nombre' => 'San Mateo'],
                ['codigo' => '0911', 'nombre' => 'Concepción Chiquirichapa'],
                ['codigo' => '0912', 'nombre' => 'San Martín Sacatepéquez'],
                ['codigo' => '0913', 'nombre' => 'Almolonga'],
                ['codigo' => '0914', 'nombre' => 'Cantel'],
                ['codigo' => '0915', 'nombre' => 'Huitán'],
                ['codigo' => '0916', 'nombre' => 'Zunil'],
                ['codigo' => '0917', 'nombre' => 'Colomba'],
                ['codigo' => '0918', 'nombre' => 'San Francisco La Unión'],
                ['codigo' => '0919', 'nombre' => 'El Palmar'],
                ['codigo' => '0920', 'nombre' => 'Coatepeque'],
                ['codigo' => '0921', 'nombre' => 'Génova'],
                ['codigo' => '0922', 'nombre' => 'Flores Costa Cuca'],
                ['codigo' => '0923', 'nombre' => 'La Esperanza'],
                ['codigo' => '0924', 'nombre' => 'Palestina de Los Altos'],
            ],
        ];

        foreach ($municipios as $deptoCode => $munis) {
            $depto = DepartamentoGeografico::where('codigo', $deptoCode)->first();

            if ($depto) {
                foreach ($munis as $muni) {
                    Municipio::firstOrCreate(
                        ['codigo' => $muni['codigo']],
                        [
                            'departamento_geo_id' => $depto->id,
                            'nombre' => $muni['nombre'],
                        ]
                    );
                }
            }
        }
    }
}
