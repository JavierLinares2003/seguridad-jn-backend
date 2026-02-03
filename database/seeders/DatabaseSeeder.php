<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed all catalogs
        $this->call(CatalogosSeeder::class);

        // Seed roles and permissions
        $this->call(RolesAndPermissionsSeeder::class);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@seguridadjn.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'estado' => true,
            ]
        );

        // Assign admin role to the admin user
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
