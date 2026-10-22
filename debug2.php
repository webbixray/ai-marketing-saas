<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Artifact;

$user = User::factory()->create();
echo "User: " . $user->id . ", tenant_id: " . $user->tenant_id . PHP_EOL;

// Create a notification for the user
$notification = Notification::factory()->create([
    'notifiable_type' => get_class($user),
    'notifiable_id' => $user->id,
]);

echo "Notification: " . $notification->id . ", tenant_id: " . $notification->tenant_id . ", notifiable_type: " . $notification->notifiable_type . ", notifiable_id: $notification->notifiable_id" . PHP_EOL;

// Refresh the user
$user->refresh();

echo "User's notifications count: " . $user->notifications->count() . PHP_EOL;

// Try to find the notification via the user's notifications relationship
$found = $user->notifications()->find($notification->id);
if ($found) {
    echo "Found notification via relationship: " . $found->id . PHP_EOL;
} else {
    echo "Could not find notification via relationship" . PHP_EOL;
}

// Now try to mark it as read via the controller method
// We'll simulate the request
use Illuminate\Http\Request;
use App\Http\Controllers\Api\NotificationController;

$request = new Request();
$request->setUserResolver(function () use ($user) {
    return $user;
});

$controller = new NotificationController();
$response = $controller->markAsRead($request, $notification->id);

echo "Response status: " . $response->getStatusCode() . PHP_EOL;
echo "Response content: " . $response->getContent() . PHP_EOL;
