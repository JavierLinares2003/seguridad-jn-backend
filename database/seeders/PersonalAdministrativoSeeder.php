<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PersonalAdministrativoSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $nuevos = [
            'view-personal-administrativo',
            'manage-personal-administrativo',
            'view-asistencia-administrativa',
            'manage-asistencia-administrativa',
        ];

        foreach ($nuevos as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($nuevos);

        $rrhh = Role::firstOrCreate(['name' => 'gerente-rrhh', 'guard_name' => 'web']);
        $rrhh->syncPermissions([
            'view-personal',
            'create-personal',
            'edit-personal',
            'view-personal-sensible',
            'view-personal-administrativo',
            'manage-personal-administrativo',
            'view-documentos',
            'upload-documentos',
            'download-documentos',
            'delete-documentos',
            'manage-personal-direccion',
            'manage-personal-familiares',
            'manage-personal-referencias',
            'manage-personal-redes-sociales',
            'manage-vacaciones',
            'view-catalogos',
            'view-asistencia-administrativa',
            'manage-asistencia-administrativa',
        ]);
    }
}
