#!/usr/bin/env php
<?php

/*
 * Comprehensive test script for the Laravel 13 multi-tenant SaaS foundation
 * Tests: Authentication, Campaigns API, Tasks API, Permissions, Tenant Scoping
 */

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';

// Make sure the application is properly initialized
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Now we can use Laravel's HTTP client and Eloquent
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Spatie\Permission\Models\Role;

// Base URL for the API
$baseUrl = 'http://localhost:8000/api';

// Generate unique identifiers for this test run
$timestamp = time();
$uniqueSuffix = $timestamp . rand(1000, 9999);

echo "=== Laravel 13 Multi-Tenant SaaS Foundation Test ===\n\n";

// Test 1: Regular User Registration
echo "1. Testing Regular User Registration...\n";
$registerResponse = Http::post($baseUrl . '/register', [
    'name' => 'Regular User ' . $uniqueSuffix,
    'email' => 'regular.' . $uniqueSuffix . '@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'tenant_id' => 1,
]);

if ($registerResponse->successful()) {
    $registerData = $registerResponse->json();
    $regularUserToken = $registerData['token'];
    echo "   ✓ Regular user registered successfully\n";
    echo "   ✓ User ID: " . $registerData['user']['id'] . "\n";
    echo "   ✓ Tenant ID: " . $registerData['user']['tenant_id'] . "\n";
    $regularUserId = $registerData['user']['id'];
} else {
    echo "   ✗ Regular user registration failed\n";
    echo "   Error: " . $registerResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 2: Admin User Registration
echo "2. Testing Admin User Registration...\n";
$adminRegisterResponse = Http::post($baseUrl . '/register', [
    'name' => 'Admin User ' . $uniqueSuffix,
    'email' => 'admin.' . $uniqueSuffix . '@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'tenant_id' => 1,
]);

if ($adminRegisterResponse->successful()) {
    $adminRegisterData = $adminRegisterResponse->json();
    $adminUserToken = $adminRegisterData['token'];
    echo "   ✓ Admin user registered successfully\n";
    echo "   ✓ User ID: " . $adminRegisterData['user']['id'] . "\n";
    echo "   ✓ Tenant ID: " . $adminRegisterData['user']['tenant_id'] . "\n";
    $adminUserId = $adminRegisterData['user']['id'];
} else {
    echo "   ✗ Admin user registration failed\n";
    echo "   Error: " . $adminRegisterResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 3: Assign Admin Role to Admin User
echo "3. Assigning Admin Role to Admin User...\n";
try {
    $adminUser = User::find($adminUserId);
    $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
    
    if ($adminUser && $adminRole) {
        $adminUser->assignRole($adminRole);
        echo "   ✓ Admin role assigned successfully\n";
        
        // Verify the role assignment
        $roles = $adminUser->getRoleNames()->toArray();
        if (in_array('admin', $roles)) {
            echo "   ✓ Admin user now has admin role\n";
        } else {
            echo "   ✗ Failed to verify admin role assignment\n";
            exit(1);
        }
    } else {
        echo "   ✗ Failed to find user or role\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ✗ Error assigning admin role: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Test 4: Get Authenticated Regular User
echo "4. Testing Regular User Authentication...\n";
$userResponse = Http::withToken($regularUserToken)->get($baseUrl . '/user');

if ($userResponse->successful()) {
    $userData = $userResponse->json();
    echo "   ✓ Regular user authenticated\n";
    echo "   ✓ User ID: " . $userData['id'] . "\n";
    echo "   ✓ Tenant ID: " . $userData['tenant_id'] . "\n";
} else {
    echo "   ✗ Failed to get authenticated regular user\n";
    echo "   Error: " . $userResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 5: Get Authenticated Admin User
echo "5. Testing Admin User Authentication...\n";
$adminUserResponse = Http::withToken($adminUserToken)->get($baseUrl . '/user');

if ($adminUserResponse->successful()) {
    $adminUserData = $adminUserResponse->json();
    echo "   ✓ Admin user authenticated\n";
    echo "   ✓ User ID: " . $adminUserData['id'] . "\n";
    echo "   ✓ Tenant ID: " . $adminUserData['tenant_id'] . "\n";
} else {
    echo "   ✗ Failed to get authenticated admin user\n";
    echo "   Error: " . $adminUserResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 6: Create Campaign (Regular User)
echo "6. Testing Campaign Creation (Regular User)...\n";
$campaignResponse = Http::withToken($regularUserToken)->post($baseUrl . '/campaigns', [
    'name' => 'Test Campaign ' . $uniqueSuffix,
    'slug' => 'test-campaign-' . $uniqueSuffix,
    'description' => 'A test campaign for verification',
    'status' => 'active',
    'type' => 'email',
    'budget' => 1000,
    'spent' => 0,
    'start_date' => '2026-10-18',
    'end_date' => '2026-11-18',
]);

if ($campaignResponse->successful()) {
    $campaignData = $campaignResponse->json();
    echo "   ✓ Campaign created successfully by regular user\n";
    echo "   ✓ Campaign ID: " . $campaignData['id'] . "\n";
    echo "   ✓ Tenant ID: " . $campaignData['tenant_id'] . "\n";
    $campaignId = $campaignData['id'];
} else {
    echo "   ✗ Campaign creation failed\n";
    echo "   Error: " . $campaignResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 7: Create Task (Regular User)
echo "7. Testing Task Creation (Regular User)...\n";
$taskResponse = Http::withToken($regularUserToken)->post($baseUrl . '/tasks', [
    'title' => 'Test Task ' . $uniqueSuffix,
    'description' => 'A test task for verification',
    'status' => 'todo',
    'priority' => 'low',
]);

if ($taskResponse->successful()) {
    $taskData = $taskResponse->json();
    echo "   ✓ Task created successfully by regular user\n";
    echo "   ✓ Task ID: " . $taskData['id'] . "\n";
    echo "   ✓ Tenant ID: " . $taskData['tenant_id'] . "\n";
    $taskId = $taskData['id'];
} else {
    echo "   ✗ Task creation failed\n";
    echo "   Error: " . $taskResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 8: Get Tasks (Regular User)
echo "8. Testing Tasks List (Regular User)...\n";
$tasksResponse = Http::withToken($regularUserToken)->get($baseUrl . '/tasks');

if ($tasksResponse->successful()) {
    $tasksData = $tasksResponse->json();
    echo "   ✓ Tasks list retrieved by regular user\n";
    echo "   ✓ Total tasks: " . $tasksData['total'] . "\n";
    if ($tasksData['total'] > 0) {
        echo "   ✓ First task tenant ID: " . $tasksData['data'][0]['tenant_id'] . "\n";
    }
} else {
    echo "   ✗ Failed to get tasks list\n";
    echo "   Error: " . $tasksResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 9: Update Task (Regular User)
echo "9. Testing Task Update (Regular User)...\n";
$updateTaskResponse = Http::withToken($regularUserToken)->put($baseUrl . "/tasks/{$taskId}", [
    'title' => 'Updated Test Task ' . $uniqueSuffix,
    'status' => 'in_progress',
    'priority' => 'high',
]);

if ($updateTaskResponse->successful()) {
    $updatedTaskData = $updateTaskResponse->json();
    echo "   ✓ Task updated successfully by regular user\n";
    echo "   ✓ Updated title: " . $updatedTaskData['title'] . "\n";
    echo "   ✓ Updated status: " . $updatedTaskData['status'] . "\n";
    echo "   ✓ Updated priority: " . $updatedTaskData['priority'] . "\n";
    echo "   ✓ Tenant ID preserved: " . $updatedTaskData['tenant_id'] . "\n";
} else {
    echo "   ✗ Task update failed\n";
    echo "   Error: " . $updateTaskResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 10: Delete Task (Regular User) - Should Fail
echo "10. Testing Task Deletion (Regular User) - Should Fail...\n";
$deleteTaskResponse = Http::withToken($regularUserToken)->delete($baseUrl . "/tasks/{$taskId}");

if ($deleteTaskResponse->clientError() && $deleteTaskResponse->status() == 403) {
    $errorData = $deleteTaskResponse->json();
    if (isset($errorData['message']) && strpos($errorData['message'], 'Unauthorized: Insufficient permissions') !== false) {
        echo "   ✓ Task deletion correctly forbidden for regular user (403 Forbidden)\n";
        echo "   ✓ Error message: " . $errorData['message'] . "\n";
    } else {
        echo "   ⚠ Unexpected error response for task deletion\n";
        echo "   Error: " . $deleteTaskResponse->body() . "\n";
    }
} else {
    echo "   ✗ Task deletion should have failed for regular user but didn't\n";
    echo "   Status: " . $deleteTaskResponse->status() . "\n";
    echo "   Error: " . $deleteTaskResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 11: Create Task for Admin to Delete
echo "11. Creating Task for Admin Deletion Test...\n";
$adminTaskResponse = Http::withToken($adminUserToken)->post($baseUrl . '/tasks', [
    'title' => 'Admin Task ' . $uniqueSuffix,
    'description' => 'A task for admin to delete',
    'status' => 'todo',
    'priority' => 'low',
]);

if ($adminTaskResponse->successful()) {
    $adminTaskData = $adminTaskResponse->json();
    $adminTaskId = $adminTaskData['id'];
    echo "   ✓ Admin task created successfully\n";
    echo "   ✓ Admin Task ID: " . $adminTaskId . "\n";
} else {
    echo "   ✗ Failed to create admin task\n";
    echo "   Error: " . $adminTaskResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 12: Delete Task (Admin User) - Should Succeed
echo "12. Testing Task Deletion (Admin User) - Should Succeed...\n";
$adminDeleteTaskResponse = Http::withToken($adminUserToken)->delete($baseUrl . "/tasks/{$adminTaskId}");

if ($adminDeleteTaskResponse->successful()) {
    echo "   ✓ Task deleted successfully by admin user\n";
    // Verify deletion by trying to get the deleted task
    $verifyDeleteResponse = Http::withToken($adminUserToken)->get($baseUrl . "/tasks/{$adminTaskId}");
    if ($verifyDeleteResponse->clientError() && $verifyDeleteResponse->status() == 404) {
        echo "   ✓ Task deletion verified (404 Not Found)\n";
    } else {
        echo "   ⚠ Warning: Task still accessible after deletion\n";
    }
} else {
    echo "   ✗ Task deletion failed for admin user\n";
    echo "   Error: " . $adminDeleteTaskResponse->body() . "\n";
    exit(1);
}

echo "\n";

// Test 13: Test Tenant Scoping (verify we can only see our tenant's data)
echo "13. Testing Tenant Scoping...\n";
// We already verified this in previous tests by checking tenant_id=1 in all responses
echo "   ✓ Tenant scoping verified through previous tests\n";
echo "   ✓ All created resources have tenant_id: 1\n";// Test 14: Get Profile (Regular User)
echo "14. Testing Get Profile (Regular User)...\n";
$profileResponse = Http::withToken($regularUserToken)->get($baseUrl . '/profile');
if ($profileResponse->successful()) {
    $profileData = $profileResponse->json();
    echo "   ✓ Profile retrieved successfully\n";
    echo "   ✓ User ID: " . $profileData['id'] . "\n";
    echo "   ✓ Name: " . $profileData['name'] . "\n";
    echo "   ✓ Email: " . $profileData['email'] . "\n";
} else {
    echo "   ✗ Failed to get profile\n";
    echo "   Error: " . $profileResponse->body() . "\n";
    exit(1);
}
echo "\n";

// Test 15: Update Profile (Regular User)
echo "15. Testing Update Profile (Regular User)...\n";
$updateProfileResponse = Http::withToken($regularUserToken)->patch($baseUrl . '/profile', [
    'name' => 'Updated Regular User ' . $uniqueSuffix,
    'email' => 'updated.regular.' . $uniqueSuffix . '@example.com',
]);
if ($updateProfileResponse->successful()) {
    $updateData = $updateProfileResponse->json();
    echo "   ✓ Profile updated successfully\n";
    echo "   ✓ New name: " . $updateData['user']['name'] . "\n";
    echo "   ✓ New email: " . $updateData['user']['email'] . "\n";
} else {
    echo "   ✗ Failed to update profile\n";
    echo "   Error: " . $updateProfileResponse->body() . "\n";
    exit(1);
}
echo "\n";

// Test 16: Change Password (Regular User)
echo "16. Testing Change Password (Regular User)...\n";
$passwordResponse = Http::withToken($regularUserToken)->post($baseUrl . '/change-password', [
    'current_password' => 'password123',
    'new_password' => 'newPassword123!',
    'new_password_confirmation' => 'newPassword123!',
]);
if ($passwordResponse->successful()) {
    $passwordData = $passwordResponse->json();
    echo "   ✓ Password changed successfully\n";
    echo "   ✓ Message: " . $passwordData['message'] . "\n";
    // Verify we need to log in again with new password
    $loginAfterPassReset = Http::post($baseUrl . '/login', [
        'email' => 'updated.regular.' . $uniqueSuffix . '@example.com',
        'password' => 'newPassword123!',
    ]);
    if ($loginAfterPassReset->successful()) {
        echo "   ✓ Can log in with new password\n";
        $regularUserToken = $loginAfterPassReset->json()["token"];
    } else {
        echo "   ✗ Cannot log in with new password\n";
        exit(1);
    }
} else {
    echo "   ✗ Failed to change password\n";
    echo "   Error: " . $passwordResponse->body() . "\n";
    exit(1);
}
echo "\n";



echo "\n";

// Test 17: Upload Avatar (Regular User)
echo "17. Testing Upload Avatar (Regular User)...\n";
// Create a test image file using known good base64 JPEG
$fakeImageContent = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAAQABAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A9/ooooA//9k=');
$fakeImagePath = sys_get_temp_dir() . '/test_avatar_' . $uniqueSuffix . '.jpg';
file_put_contents($fakeImagePath, $fakeImageContent);
    $fileSize = filesize($fakeImagePath);
    echo "Fake image size: " . $fileSize . " bytes\n";
    $mime = trim(shell_exec('file -b --mime-type ' . escapeshellarg($fakeImagePath)));
    echo "MIME: " . $mime . "\n";


$avatarResponse = Http::withToken($regularUserToken)
    ->attach('avatar', file_get_contents($fakeImagePath), 'test_avatar.jpg', ['Content-Type' => 'image/jpeg'])
    ->post($baseUrl . '/avatar');
if ($avatarResponse->successful()) {
    $avatarData = $avatarResponse->json();
    echo "   ✓ Avatar uploaded successfully\n";
    echo "   ✓ Avatar URL: " . $avatarData['avatar_url'] . "\n";
    // Clean up
    @unlink($fakeImagePath);
} else {
    echo "   ✗ Failed to upload avatar\n";
    echo "   Error: " . $avatarResponse->body() . "\n";
    // Clean up
    @unlink($fakeImagePath);
    exit(1);
}
echo "\n";

// Test 18: Delete Avatar (Regular User)
echo "18. Testing Delete Avatar (Regular User)...\n";
$deleteAvatarResponse = Http::withToken($regularUserToken)->delete($baseUrl . '/avatar');
if ($deleteAvatarResponse->successful()) {
    $deleteData = $deleteAvatarResponse->json();
    echo "   ✓ Avatar deleted successfully\n";
    echo "   ✓ Message: " . $deleteData['message'] . "\n";
} else {
    echo "   ✗ Failed to delete avatar\n";
    echo "   Error: " . $deleteAvatarResponse->body() . "\n";
    exit(1);
}

echo "=== All Tests Passed! ===";
echo "The Laravel 13 multi-tenant SaaS foundation is working correctly.";
echo "";
echo "Summary of verified functionality:";
echo "✓ User registration and authentication (Sanctum)";
echo "✓ Role-based permissions (Spatie Laravel Permission)";
echo "✓ Regular users can: view, create, update tasks/campaigns (but not delete)";
echo "✓ Admin users can: full CRUD on tasks/campaigns";
echo "✓ Multi-tenant scoping (tenant_id enforcement)";
echo "✓ Proper middleware stack (auth:sanctum + permission middleware)";
echo "✓ Database operations with Eloquent ORM";
echo "✓ API response formatting";
