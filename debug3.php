<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$user = User::factory()->create();
Notification::factory()->count(3)->create([
    'notifiable_type' => get_class($user),
    'notifiable_id' => $user->id,
]);

$request = new Request();
$request->setUserResolver(function () use ($user) {
    return $user;
});

$controller = new \App\Http\Controllers\Api\NotificationController();
$response = $controller->unreadCount($request);

echo "Response: " . $response->getContent() . PHP_EOL;
