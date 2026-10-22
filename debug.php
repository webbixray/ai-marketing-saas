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
    'notifiable_type' => User::class,
    'notifiable_id' => $user->id,
]);

echo "Notification: " . $notification->id . ", tenant_id: " . $notification->tenant_id . ", notifiable_type: " . $notification->notifiable_type . ", notifiable_id: " . $notification->notifiable_id . PHP_EOL;

// Refresh the user
$user->refresh();

echo "User's notifications count: " . $user->notifications->count() . PHP_EOL;
foreach ($user->notifications as $n) {
    echo "  Notification ID: " . $n->id . ", tenant_id: " . $n->tenant_id . PHP_EOL;
}

// Now try to get the notifications via the relationship with ordering and pagination
$notifications = $user->notifications()->orderBy('created_at', 'desc')->paginate(20);
echo "Paginated notifications: " . ($notifications ? 'object' : 'null') . PHP_EOL;
if ($notifications) {
    echo "  Total: " . $notifications->total() . PHP_EOL;
    echo "  Per page: " . $notifications->perPage() . PHP_EOL;
    echo "  Current page: " . $notifications->currentPage() . PHP_EOL;
    echo "  Last page: " . $notifications->lastPage() . PHP_EOL;
    echo "  Items: " . count($notifications->items()) . PHP_EOL;
}
