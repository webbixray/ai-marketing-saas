<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$user = \App\Models\User::factory()->create();
var_dump($user->toArray());

$notification = \App\Models\Notification::factory()->create([
    'notifiable_type' => \App\Models\User::class,
    'notifiable_id' => $user->id,
]);

var_dump($notification->toArray());

$user->refresh();

var_dump($user->notifications->toArray());
