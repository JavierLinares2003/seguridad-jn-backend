<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-users', only: ['index', 'show']),
            new Middleware('permission:create-users', only: ['store']),
            new Middleware('permission:edit-users', only: ['update', 'toggleEstado', 'assignRole', 'removeRole']),
            new Middleware('permission:delete-users', only: ['destroy']),
        ];
    }

    /**
     * List all users with their roles.
     *
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles');

        // Filtro por estado
        if ($request->has('estado')) {
            $query->where('estado', $request->boolean('estado'));
        }

        // Filtro por rol
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Búsqueda por nombre o email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Create a new user.
     *
     * POST /api/v1/users
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'estado' => true,
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'estado' => $user->estado,
                'roles' => $user->getRoleNames(),
                'created_at' => $user->created_at,
            ],
        ], 201);
    }

    /**
     * Show a specific user.
     *
     * GET /api/v1/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'estado' => $user->estado,
                'ultimo_login' => $user->ultimo_login,
                'email_verified_at' => $user->email_verified_at,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Update a user.
     *
     * PUT /api/v1/users/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|min:8|confirmed',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'estado' => $user->estado,
                'roles' => $user->getRoleNames(),
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Delete a user.
     *
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar tu propia cuenta.',
            ], 403);
        }

        // Revoke all tokens
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente.',
        ]);
    }

    /**
     * Toggle user active status.
     *
     * PATCH /api/v1/users/{id}/toggle-estado
     */
    public function toggleEstado(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        // Prevent self-deactivation
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes desactivar tu propia cuenta.',
            ], 403);
        }

        $user->update(['estado' => !$user->estado]);

        // If deactivated, revoke all tokens
        if (!$user->estado) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $user->estado ? 'Usuario activado.' : 'Usuario desactivado.',
            'data' => [
                'id' => $user->id,
                'estado' => $user->estado,
            ],
        ]);
    }

    /**
     * Assign a role to user.
     *
     * POST /api/v1/users/{id}/roles
     */
    public function assignRole(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'success' => true,
            'message' => "Rol '{$validated['role']}' asignado exitosamente.",
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * Remove a role from user.
     *
     * DELETE /api/v1/users/{id}/roles/{role}
     */
    public function removeRole(Request $request, int $id, string $role): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        // Verify role exists
        if (!Role::where('name', $role)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado.',
            ], 404);
        }

        // Prevent removing own admin role
        if ($user->id === $request->user()->id && $role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes remover tu propio rol de administrador.',
            ], 403);
        }

        $user->removeRole($role);

        return response()->json([
            'success' => true,
            'message' => "Rol '{$role}' removido exitosamente.",
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    /**
     * Sync all roles for a user (replace all roles).
     *
     * PUT /api/v1/users/{id}/roles
     */
    public function syncRoles(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
        ]);

        // Prevent removing own admin role
        if ($user->id === $request->user()->id && !in_array('admin', $validated['roles'])) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes remover tu propio rol de administrador.',
            ], 403);
        }

        $user->syncRoles($validated['roles']);

        return response()->json([
            'success' => true,
            'message' => 'Roles actualizados exitosamente.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }
}
