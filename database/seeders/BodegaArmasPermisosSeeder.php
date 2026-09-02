<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BodegaArmasPermisosSeeder extends Seeder
{
    /**
     * Permisos de armas, independientes de bodega.
     * Seguro para producción: no resetea roles ni claves.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $nuevos = [
            'view-armas',
            'manage-armas',
        ];

        foreach ($nuevos as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        Permission::firstOrCreate(['name' => 'view-proyectos', 'guard_name' => 'web']);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($nuevos);

        $juridica = Role::firstOrCreate(['name' => 'gerencia-juridica', 'guard_name' => 'web']);
        $juridica->syncPermissions([
            'view-armas',
            'manage-armas',
            'view-proyectos',
        ]);
    }
}
