<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate user and return token.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        // Verificar si el usuario existe
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas.',
                'error' => 'invalid_credentials',
            ], 401);
        }

        // Verificar contraseña
        if (!Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas.',
                'error' => 'invalid_credentials',
            ], 401);
        }

        // Verificar si el usuario está activo
        if (!$user->estado) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta está desactivada. Contacta al administrador.',
                'error' => 'account_disabled',
            ], 401);
        }

        // Actualizar último login
        $user->update(['ultimo_login' => now()]);

        // Revocar tokens anteriores (opcional, para single session)
        // $user->tokens()->delete();

        // Crear nuevo token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'estado' => $user->estado,
                    'ultimo_login' => $user->ultimo_login,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /**
     * Get authenticated user info.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'estado' => $user->estado,
                'ultimo_login' => $user->ultimo_login,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 200);
    }

    /**
     * Logout user and revoke current token.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Revocar solo el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente.',
        ], 200);
    }

    /**
     * Logout user from all devices (revoke all tokens).
     *
     * POST /api/v1/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Revocar todos los tokens del usuario
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Todas las sesiones han sido cerradas.',
        ], 200);
    }

    /**
     * Change user password.
     *
     * POST /api/v1/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta.',
                'error' => 'invalid_current_password',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente.',
        ], 200);
    }
}
