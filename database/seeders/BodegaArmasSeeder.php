<?php

namespace Database\Seeders;

use App\Models\BodegaArma;
use App\Services\BodegaService;
use Illuminate\Database\Seeder;

class BodegaArmasSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(BodegaService::class);

        $arma = BodegaArma::updateOrCreate(
            ['serie' => '01690E'],
            [
                'codigo_interno' => '15',
                'tipo' => 'revolver',
                'marca' => 'RANGER',
                'modelo' => '102',
                'tenencia' => '2782169',
                'portacion' => '794961',
                'vencimiento' => '2026-05-22',
                'responsable_nombre' => 'CLAUDIA ESCOBAR',
                'estado' => 'asignada',
            ]
        );

        $service->asegurarCodigoArma($arma);
    }
}