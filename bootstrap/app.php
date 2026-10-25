<?php

use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\CspNonce;
use App\Http\Middleware\SecurityHeaders;
use App\Providers\AIServiceProvider;
use App\Providers\EnterpriseServiceProvider;
use App\Providers\GlobalServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => CheckAdminRole::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Add CSP nonce middleware (must run before SecurityHeaders)
        $middleware->prepend(CspNonce::class);

        // Add global security headers middleware
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withProviders([
        AIServiceProvider::class,
        EnterpriseServiceProvider::class,
        GlobalServiceProvider::class,
    ])
    ->create();
