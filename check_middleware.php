<?php

require __DIR__.'/vendor/autoload.php';

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->bootstrapWith([/* bootstrap */]);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$router = $app->make(Illuminate\Contracts\Routing\Registrar::class);

$middleware = $router->getMiddleware();

if (array_key_exists('permission', $middleware)) {
    echo "found\n";
    echo $middleware['permission'] . "\n";
} else {
    echo "not found\n";
    print_r(array_keys($middleware));
}