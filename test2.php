<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Artifact;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class Test extends BaseTestCase
{
    use RefreshDatabase;

    public function testGetNotifications()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create a notification for the user
        Notification::factory()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);

        $response = $this->getJson('/api/notifications');

        var_dump($response->json());
    }
}

$test = new Test('testGetNotifications');
$test->testGetNotifications();
