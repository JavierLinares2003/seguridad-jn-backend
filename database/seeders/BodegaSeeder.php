<?php

namespace Database\Seeders;

use App\Models\BodegaCategoria;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BodegaSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['view-bodega', 'manage-bodega'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        if ($admin = Role::where('name', 'admin')->first()) {
            $admin->givePermissionTo(['view-bodega', 'manage-bodega']);
        }
        if ($ops = Role::where('name', 'operaciones')->first()) {
            $ops->givePermissionTo(['view-bodega', 'manage-bodega']);
        }
        if ($conta = Role::where('name', 'contabilidad')->first()) {
            $conta->givePermissionTo(['view-bodega', 'manage-bodega']);
        }

        $categorias = [
            ['codigo' => 'uniforme_agentes', 'nombre' => 'Uniforme Agentes', 'icono' => 'mdi-tshirt-crew', 'orden' => 1],
            ['codigo' => 'uniforme_admin', 'nombre' => 'Uniforme Administración', 'icono' => 'mdi-tie', 'orden' => 2],
            ['codigo' => 'sueter_militar', 'nombre' => 'Suéter Militar', 'icono' => 'mdi-hoodie', 'orden' => 3],
            ['codigo' => 'libreria', 'nombre' => 'Librería', 'icono' => 'mdi-bookshelf', 'orden' => 4],
            ['codigo' => 'accesorios_uniforme', 'nombre' => 'Accesorios Uniforme', 'icono' => 'mdi-badge-account', 'orden' => 5],
            ['codigo' => 'accesorios_puesto', 'nombre' => 'Accesorios Puesto', 'icono' => 'mdi-radio-handheld', 'orden' => 6],
            ['codigo' => 'equipo_lluvia', 'nombre' => 'Equipo de Lluvia', 'icono' => 'mdi-weather-pouring', 'orden' => 7],
            ['codigo' => 'limpieza', 'nombre' => 'Limpieza', 'icono' => 'mdi-broom', 'orden' => 8],
            ['codigo' => 'mecanico', 'nombre' => 'Mecánico', 'icono' => 'mdi-wrench', 'orden' => 9],
        ];

        foreach ($categorias as $cat) {
            BodegaCategoria::updateOrCreate(
                ['codigo' => $cat['codigo']],
                array_merge($cat, ['activo' => true])
            );
        }

        // Cargar inventario desde Excel (o demo mínimo si no hay archivo)
        $this->call(BodegaImportExcelSeeder::class);
        $this->call(BodegaProveedoresSeeder::class);
        $this->call(BodegaKitsYLiquidacionSeeder::class);
        $this->call(BodegaArmasSeeder::class);
    }
}
