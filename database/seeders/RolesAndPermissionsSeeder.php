<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions by module
        $permissions = [
            // User management
            'manage-users',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Personal management
            'view-personal',
            'create-personal',
            'edit-personal',
            'delete-personal',
            'restore-personal',

            // Personal documents
            'view-documentos',
            'upload-documentos',
            'download-documentos',
            'delete-documentos',

            // Personal related data (direcciones, familiares, referencias, redes sociales)
            'manage-personal-direccion',
            'manage-personal-familiares',
            'manage-personal-referencias',
            'manage-personal-redes-sociales',

            // Catalogs
            'view-catalogos',
            'manage-catalogos',

            // Roles and permissions
            'manage-roles',
            'manage-permissions',

            // Admin dashboard
            'access-admin-dashboard',

            // Bitacora/Audit Log
            'view-bitacora',

            // PROJECTS Module
            'view-proyectos',
            'create-proyectos',
            'edit-proyectos',
            'delete-proyectos',
            'restore-proyectos',
            'manage-proyectos-contactos',
            'manage-proyectos-inventario',
            'manage-proyectos-configuracion', // Puestos/Margen
            'manage-proyectos-asignaciones',  // Assigning staff

            // OPERATIONS Module
            'view-operaciones',
            'manage-asistencia',
            'manage-asignaciones',
            'manage-transacciones',
            'manage-prestamos',
            'view-alertas-cobertura',

            // PAYROLL Module
            'view-planillas',
            'create-planillas',
            'approve-planillas',
            'export-planillas',
            'cancel-planillas',
            'mark-planillas-paid',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $this->createAdminRole($permissions);
        $this->createSupervisorRole();
        $this->createOperadorRole();
        $this->createGestorPersonalRole();
        $this->createGestorProyectosRole();
    }

    /**
     * Create admin role with all permissions except Operations module.
     */
    private function createAdminRole(array $allPermissions): void
    {
        // Exclude Operations module permissions temporarily
        $excludedPermissions = [
            'view-operaciones',
            'manage-asistencia',
            'manage-asignaciones',
            'manage-transacciones',
            'manage-prestamos',
            'view-alertas-cobertura',
        ];

        $adminPermissions = array_filter($allPermissions, function ($permission) use ($excludedPermissions) {
            return !in_array($permission, $excludedPermissions);
        });

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($adminPermissions);
    }

    /**
     * Create supervisor role with limited permissions.
     */
    private function createSupervisorRole(): void
    {
        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'view-users',
            'view-personal',
            'create-personal',
            'edit-personal',
            'view-documentos',
            'upload-documentos',
            'download-documentos',
            'manage-personal-direccion',
            'manage-personal-familiares',
            'manage-personal-referencias',
            'manage-personal-redes-sociales',
            'view-catalogos',

            // Projects
            'view-proyectos',
            'create-proyectos',
            'edit-proyectos',
            'manage-proyectos-contactos',
            'manage-proyectos-inventario',
            'manage-proyectos-configuracion',
            'manage-proyectos-asignaciones',

            // Operations
            'view-operaciones',
            'manage-asistencia',
            'manage-asignaciones',
            'manage-transacciones',
            'manage-prestamos',
            'view-alertas-cobertura',

            // Payroll
            'view-planillas',
            'create-planillas',
            'approve-planillas',
            'export-planillas',
        ]);
    }

    /**
     * Create operador role with basic permissions.
     */
    private function createOperadorRole(): void
    {
        $operador = Role::firstOrCreate(['name' => 'operador', 'guard_name' => 'web']);
        $operador->syncPermissions([
            'view-personal',
            'create-personal',
            'view-documentos',
            'upload-documentos',
            'download-documentos',
            'manage-personal-direccion',
            'manage-personal-familiares',
            'manage-personal-referencias',
            'manage-personal-redes-sociales',
            'view-catalogos',

            // Projects (View only)
            'view-proyectos',

            // Operations (View only)
            'view-operaciones',
        ]);
    }

    /**
     * Create gestor-personal role with only personal module permissions.
     */
    private function createGestorPersonalRole(): void
    {
        $gestorPersonal = Role::firstOrCreate(['name' => 'gestor-personal', 'guard_name' => 'web']);
        $gestorPersonal->syncPermissions([
            // Personal management (full access)
            'view-personal',
            'create-personal',
            'edit-personal',
            'delete-personal',
            'restore-personal',

            // Personal documents (full access)
            'view-documentos',
            'upload-documentos',
            'download-documentos',
            'delete-documentos',

            // Personal related data (full access)
            'manage-personal-direccion',
            'manage-personal-familiares',
            'manage-personal-referencias',
            'manage-personal-redes-sociales',

            // Catalogs (view only - needed for forms)
            'view-catalogos',
        ]);
    }

    /**
     * Create gestor-proyectos role with only projects module permissions.
     */
    private function createGestorProyectosRole(): void
    {
        $gestorProyectos = Role::firstOrCreate(['name' => 'gestor-proyectos', 'guard_name' => 'web']);
        $gestorProyectos->syncPermissions([
            // Projects management (full access)
            'view-proyectos',
            'create-proyectos',
            'edit-proyectos',
            'delete-proyectos',
            'restore-proyectos',
            'manage-proyectos-contactos',
            'manage-proyectos-inventario',
            'manage-proyectos-configuracion',
            'manage-proyectos-asignaciones',

            // Catalogs (view only - needed for forms)
            'view-catalogos',
        ]);
    }
}
