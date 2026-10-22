# Troubleshooting Notes: Permission Middleware String Alias Issue

## Problem
When using string middleware alias `'permission'` in route definitions, Laravel throws:
```
Target class [permission] does not exist.
```

## Verification Steps That Showed It Should Work
1. ✅ Confirmed `Spatie\Permission\PermissionServiceProvider::class` is in `config/app.php` providers
2. ✅ Verified `PermissionMiddleware` class exists at `/vendor/spatie/laravel-permission/src/Middleware/PermissionMiddleware.php`
3. ✅ Confirmed `Kernel.php` has correct route middleware mapping:
   ```php
   'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
   ```
4. ✅ Verified through reflection that `$kernel->routeMiddleware()['permission']` resolves to `Spatie\Permission\Middleware\PermissionMiddleware`
5. ✅ Confirmed `PermissionMiddleware` class can be resolved directly from container: `app()->make('Spatie\Permission\Middleware\PermissionMiddleware')`
6. ✅ Cleared all caches: config, route, application, compiled, route cache

## Working Solution
Using the full class name instead of string alias works correctly:
```php
->middleware([
    'auth:sanctum', 
    \Spatie\Permission\Middleware\PermissionMiddleware::class . ':view tasks|create tasks|update tasks|delete tasks'
])
```

## Root Cause Investigation Needed
The issue appears to be specific to Laravel's string alias resolution for route middleware during the pipeline execution phase, despite the alias being correctly registered in the kernel. Further investigation would require:
- Adding debug listeners to container resolution
- Checking if any service providers modify middleware after boot
- Examining differences between reflection-based access and actual pipeline middleware resolution

## Recommendation
For immediate development progress, use the full class name approach in route definitions. The string alias issue should be investigated separately when time permits.