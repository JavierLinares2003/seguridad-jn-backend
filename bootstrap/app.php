<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stateful API middleware for Sanctum SPA authentication
        $middleware->statefulApi();

        // Middleware aliases
        $middleware->alias([
            // Sanctum
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,

            // Spatie Permission
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,

            // Custom API middleware (alternative JSON responses)
            'api.auth' => \App\Http\Middleware\EnsureApiAuthenticated::class,
            'api.role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'api.permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
