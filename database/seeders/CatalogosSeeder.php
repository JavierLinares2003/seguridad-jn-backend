<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CatalogosSeeder extends Seeder
{
    /**
     * Seed all catalog tables.
     */
    public function run(): void
    {
        $this->call([
            EstadoCivilSeeder::class,
            TipoSangreSeeder::class,
            SexoSeeder::class,
            TipoContratacionSeeder::class,
            TipoPagoSeeder::class,
            DepartamentoSeeder::class,
            DepartamentoGeograficoSeeder::class,
            MunicipioSeeder::class,
            ParentescoSeeder::class,
            RedSocialSeeder::class,
            NivelEstudioSeeder::class,
            TipoPersonalSeeder::class,
            TurnoSeeder::class,
            TipoProyectoSeeder::class,
            TipoDocumentoPersonalSeeder::class,
            TipoDocumentoProyectoSeeder::class,
            PeriodicidadPagoSeeder::class,
            TipoDocumentoFacturacionSeeder::class,
        ]);
    }
}
