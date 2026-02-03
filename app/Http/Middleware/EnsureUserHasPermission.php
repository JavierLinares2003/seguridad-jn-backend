<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the authenticated user has the required permission(s).
     * Returns JSON response for unauthorized access.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => 'unauthenticated',
            ], 401);
        }

        foreach ($permissions as $permission) {
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have the required permission to access this resource.',
                    'error' => 'forbidden',
                    'required_permission' => $permission,
                ], 403);
            }
        }

        return $next($request);
    }
}
