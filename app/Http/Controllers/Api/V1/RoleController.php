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
        'view-asistencia-administrativa' => 'Asistencia administrativa',
        'view-planillas' => 'Planillas',
        'view-bodega' => 'Bodega / Inventario',
        'view-armas' => 'Armas',
        'view-users' => 'Usuarios',
        'manage-roles' => 'Roles y vistas',
        'view-bitacora' => 'Bitácora',
        'manage-vacaciones' => 'Vacaciones',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage-roles', only: ['index', 'show', 'permissions', 'syncPermissions', 'store', 'destroy']),
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

        $this->applyVistas($role, $validator->validated()['vistas']);

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

    /**
     * Create a new role with optional menu views.
     *
     * POST /api/v1/roles
     * Body: { name: "bodega", vistas: ["view-bodega"] }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[\pL\pN]+([\pL\pN\s\-]*[\pL\pN])?$/u'],
            'vistas' => ['nullable', 'array'],
            'vistas.*' => ['string', 'in:' . implode(',', array_keys(self::MENU_PERMISSIONS))],
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.min' => 'El nombre del rol debe tener al menos 3 caracteres.',
            'name.max' => 'El nombre del rol no puede superar 50 caracteres.',
            'name.regex' => 'Usa solo letras, números, espacios o guiones.',
            'vistas.*.in' => 'Una o más vistas no son válidas.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $name = mb_strtolower(trim(preg_replace('/\s+/', ' ', $validator->validated()['name'])));

        if ($name === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'El rol admin ya existe y no se puede duplicar.',
            ], 422);
        }

        if (Role::where('name', $name)->where('guard_name', 'web')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un rol con ese nombre.',
            ], 422);
        }

        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        $this->applyVistas($role, $validator->validated()['vistas'] ?? []);
        $role->load('permissions');
        $permissionNames = $role->permissions->pluck('name');

        return response()->json([
            'success' => true,
            'message' => 'Rol creado correctamente.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $permissionNames->count(),
                'users_count' => 0,
                'permissions' => $permissionNames,
                'vistas' => collect(self::MENU_PERMISSIONS)
                    ->filter(fn ($label, $perm) => $permissionNames->contains($perm))
                    ->values()
                    ->all(),
            ],
        ], 201);
    }

    /**
     * Delete a role that is not in use.
     *
     * DELETE /api/v1/roles/{id}
     */
    public function destroy(int $id): JsonResponse
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
                'message' => 'El rol admin no se puede eliminar.',
            ], 422);
        }

        $usersCount = User::role($role->name)->count();
        if ($usersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: hay {$usersCount} usuario(s) con este rol. Cámbiales el rol primero.",
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rol eliminado correctamente.',
        ]);
    }

    /**
     * Assign menu views to a role, keeping unrelated permissions.
     */
    private function applyVistas(Role $role, array $selectedMenu): void
    {
        $menuPerms = array_keys(self::MENU_PERMISSIONS);
        $role->loadMissing('permissions');
        $current = $role->permissions->pluck('name')->all();
        $nonMenu = array_values(array_diff($current, $menuPerms));

        $relatedByView = [
            'view-bodega' => ['manage-bodega'],
            'view-armas' => ['manage-armas'],
            'view-proyectos' => ['create-proyectos', 'edit-proyectos'],
            'view-asistencia-administrativa' => ['manage-asistencia-administrativa'],
        ];
        $allRelated = [];
        foreach ($relatedByView as $actions) {
            $allRelated = array_merge($allRelated, $actions);
        }
        $nonMenu = array_values(array_diff($nonMenu, array_values(array_unique($allRelated))));

        $extra = [];
        foreach ($selectedMenu as $vista) {
            if (isset($relatedByView[$vista])) {
                $extra = array_merge($extra, $relatedByView[$vista]);
            }
        }

        foreach (array_merge($selectedMenu, $extra) as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        $role->syncPermissions(array_values(array_unique(array_merge($nonMenu, $selectedMenu, $extra))));
    }
}
