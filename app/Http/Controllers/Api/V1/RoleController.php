<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    /**
     * Permisos que representan pantallas/menú del sistema.
     */
    public const MENU_PERMISSIONS = [
        'view-personal' => 'Personal',
        'view-proyectos' => 'Proyectos',
        'view-operaciones' => 'Operaciones / Asistencia',
        'view-planillas' => 'Planillas',
        'view-users' => 'Usuarios',
        'manage-roles' => 'Roles y vistas',
        'view-bitacora' => 'Bitácora',
        'manage-vacaciones' => 'Vacaciones',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage-roles', only: ['index', 'show', 'permissions', 'syncPermissions']),
        ];
    }

    /**
     * List all available roles.
     *
     * GET /api/v1/roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->withCount('permissions')->get();

        return response()->json([
            'success' => true,
            'data' => $roles->map(function ($role) {
                $permissionNames = $role->permissions->pluck('name');
                $vistas = collect(self::MENU_PERMISSIONS)
                    ->filter(fn ($label, $perm) => $permissionNames->contains($perm))
                    ->values()
                    ->all();

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions_count,
                    'users_count' => User::role($role->name)->count(),
                    'permissions' => $permissionNames,
                    'vistas' => $vistas,
                ];
            }),
        ]);
    }

    /**
     * Show a specific role with its permissions.
     *
     * GET /api/v1/roles/{id}
     */
    public function show(int $id): JsonResponse
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado.',
            ], 404);
        }

        $permissionNames = $role->permissions->pluck('name');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $permissionNames,
                'vistas' => collect(self::MENU_PERMISSIONS)
                    ->filter(fn ($label, $perm) => $permissionNames->contains($perm))
                    ->map(fn ($label, $perm) => ['permission' => $perm, 'label' => $label])
                    ->values(),
                'menu_permissions' => self::MENU_PERMISSIONS,
            ],
        ]);
    }

    /**
     * List menu/view permissions available to assign.
     *
     * GET /api/v1/roles/permissions/menu
     */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => collect(self::MENU_PERMISSIONS)->map(fn ($label, $perm) => [
                'permission' => $perm,
                'label' => $label,
            ])->values(),
        ]);
    }

    /**
     * Sync menu/view permissions for a role (keeps non-menu permissions).
     *
     * PUT /api/v1/roles/{id}/permissions
     * Body: { vistas: ["view-personal", "view-proyectos", ...] }
     */
    public function syncPermissions(Request $request, int $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado.',
            ], 404);
        }

        if ($role->name === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden modificar las vistas del rol admin.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'vistas' => ['required', 'array'],
            'vistas.*' => ['string', 'in:' . implode(',', array_keys(self::MENU_PERMISSIONS))],
        ], [
            'vistas.required' => 'Debe enviar el listado de vistas.',
            'vistas.*.in' => 'Una o más vistas no son válidas.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $menuPerms = array_keys(self::MENU_PERMISSIONS);
        $current = $role->permissions->pluck('name')->all();
        $nonMenu = array_values(array_diff($current, $menuPerms));
        $selectedMenu = $validator->validated()['vistas'];

        // Ensure permissions exist
        foreach ($selectedMenu as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        $role->syncPermissions(array_values(array_unique(array_merge($nonMenu, $selectedMenu))));

        $role->load('permissions');
        $permissionNames = $role->permissions->pluck('name');

        return response()->json([
            'success' => true,
            'message' => 'Vistas del rol actualizadas correctamente.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $permissionNames,
                'vistas' => collect(self::MENU_PERMISSIONS)
                    ->filter(fn ($label, $perm) => $permissionNames->contains($perm))
                    ->map(fn ($label, $perm) => ['permission' => $perm, 'label' => $label])
                    ->values(),
            ],
        ]);
    }
}
