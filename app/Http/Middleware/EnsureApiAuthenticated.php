<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the request has a valid Sanctum token and
     * returns appropriate JSON responses for API consumers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid API token.',
                'error' => 'unauthenticated',
            ], 401);
        }

        return $next($request);
    }
}
