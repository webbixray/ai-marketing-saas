<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\Campaign;

class ReportingTest extends TestCase
{
    use WithFaker, DatabaseMigrations;

    public function test_revenue_report_returns_correct_data()
    {
        // We assume there is a way to create revenue data, perhaps through subscriptions.
        // For the purpose of this test, we'll just hit the endpoint and check the structure.
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson($this->apiUrl('/reports/revenue'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'mrr',
            'currency',
            'plan_breakdown' => [
                '*' => ['plan_name', 'amount', 'subscriber_count']
            ]
        ]);
    }

    public function test_user_growth_report_returns_correct_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson($this->apiUrl('/reports/user-growth'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user_growth' => [
                '*' => ['period', 'new_users']
            ]
        ]);
    }

    public function test_subscription_growth_report_returns_correct_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson($this->apiUrl('/reports/subscription/growth'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'subscription_growth' => [
                '*' => ['period', 'new_subscriptions']
            ]
        ]);
    }

    public function test_task_stats_report_returns_correct_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create some tasks for the user (same tenant, so the tenant scope applies)
        Task::factory()->count(5)->create([
            'assigned_to' => $user->id,
            'tenant_id' => $user->tenant_id,
            'status' => 'completed',
        ]);
        Task::factory()->count(3)->create([
            'assigned_to' => $user->id,
            'tenant_id' => $user->tenant_id,
            'status' => 'pending',
        ]);

        $response = $this->getJson($this->apiUrl('/reports/task-stats'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'task_stats' => [
                'total',
                'completed',
                'pending',
                'completion_rate'
            ]
        ]);

        // Check the values
        $response->assertJson([
            'task_stats' => [
                'total' => 8,
                'completed' => 5,
                'pending' => 3,
                'completion_rate' => 62.5, // 5/8*100 = 62.5
            ]
        ]);
    }

    public function test_campaign_stats_report_returns_correct_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create some campaigns
        Campaign::factory()->count(4)->create([
            'tenant_id' => $user->tenant_id,
            'status' => 'active',
        ]);
        Campaign::factory()->count(2)->create([
            'tenant_id' => $user->tenant_id,
            'status' => 'paused',
        ]);
        Campaign::factory()->count(1)->create([
            'tenant_id' => $user->tenant_id,
            'status' => 'archived',
        ]);

        $response = $this->getJson($this->apiUrl('/reports/campaign-stats'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'campaign_stats' => [
                'total',
                'active',
                'paused',
                'archived'
            ]
        ]);

        // Check the values
        $response->assertJson([
            'campaign_stats' => [
                'total' => 7,
                'active' => 4,
                'paused' => 2,
                'archived' => 1,
            ]
        ]);
    }

    public function test_user_activity_report_returns_correct_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // We assume the endpoint returns the count of active users in the last 30 days.
        // Since we just created the user, and we assume 'active' means logged in or something,
        // we'll just check the structure.
        $response = $this->getJson($this->apiUrl('/reports/user/activity'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'active_users_last_30_days'
        ]);
    }
}
