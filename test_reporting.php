#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

$baseUrl = 'http://localhost:8000/api';
$timestamp = time();
$uniqueSuffix = $timestamp . rand(1000, 9999);

echo "Registering test user...\n";
$reg = Http::post($baseUrl . '/register', [
    'name' => 'Test User',
    'email' => 'test.' . $uniqueSuffix . '@example.com',
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
    'tenant_id' => 1,
]);
if (!$reg->successful()) {
    echo "Registration failed: " . $reg->body() . "\n";
    exit(1);
}
$token = $reg->json()['token'];
echo "Got token: $token\n\n";

$headers = ['Authorization' => 'Bearer ' . $token];

$endpoints = [
    'revenue' => '/reports/revenue',
    'revenue/trend' => '/reports/revenue/trend',
    'user-growth' => '/reports/user-growth',
    'subscription/growth' => '/reports/subscription/growth',
    'task-stats' => '/reports/task-stats',
    'task/completion-trend' => '/reports/task/completion-trend',
    'campaign-stats' => '/reports/campaign-stats',
    'campaign/creation-trend' => '/reports/campaign/creation-trend',
    'user/activity' => '/reports/user/activity',
];

foreach ($endpoints as $name => $path) {
    echo "Testing $name...\n";
    $res = Http::withToken($token)->get($baseUrl . $path);
    if ($res->successful()) {
        echo "  SUCCESS: " . json_encode($res->json()) . "\n";
    } else {
        echo "  FAILED: " . $res->status() . " - " . $res->body() . "\n";
    }
    echo "\n";
}
echo "All reporting endpoints tested.\n";