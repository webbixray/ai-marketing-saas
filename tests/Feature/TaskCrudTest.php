<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tasks module: full CRUD via /api/v1/tasks plus the permission checks
 * in TaskController (view/create/update/delete tasks).
 *
 * Cross-tenant read isolation is covered by TenantIsolationTest; this
 * file adds same-tenant happy paths and the cross-tenant write case.
 */
class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Same role/permission setup as RolePermissionSeeder (tests do not seed).
        foreach (['view tasks', 'create tasks', 'update tasks', 'delete tasks'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
        }
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        $role->syncPermissions(['view tasks', 'create tasks', 'update tasks', 'delete tasks']);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole($role);
    }

    private function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Write launch copy',
            'description' => 'Draft the landing page copy',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => '2026-12-01',
            'assigned_to' => $this->user->id,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Permissions
    // ---------------------------------------------------------------

    public function test_index_is_forbidden_without_task_permissions(): void
    {
        // Fresh user with no roles/permissions at all.
        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($nobody, 'sanctum')
            ->getJson($this->apiUrl('/tasks'))
            ->assertForbidden();
    }

    public function test_show_is_forbidden_without_view_permission(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
        ]);

        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($nobody, 'sanctum')
            ->getJson($this->apiUrl("/tasks/{$task->id}"))
            ->assertForbidden();
    }

    public function test_store_is_forbidden_without_create_permission(): void
    {
        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($nobody, 'sanctum')
            ->postJson($this->apiUrl('/tasks'), $this->taskPayload())
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_index_lists_and_filters_tasks(): void
    {
        Task::factory()->create(['tenant_id' => $this->tenant->id, 'assigned_to' => $this->user->id, 'status' => 'todo']);
        Task::factory()->create(['tenant_id' => $this->tenant->id, 'assigned_to' => $this->user->id, 'status' => 'todo']);
        Task::factory()->create(['tenant_id' => $this->tenant->id, 'assigned_to' => $this->user->id, 'status' => 'completed']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/tasks'))
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/tasks?status=todo'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ---------------------------------------------------------------
    // Store
    // ---------------------------------------------------------------

    public function test_store_task_returns_201_with_tenant(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/tasks'), $this->taskPayload());

        $response->assertCreated()
            ->assertJsonPath('title', 'Write launch copy')
            ->assertJsonPath('tenant_id', $this->tenant->id)
            ->assertJsonStructure(['assigned_user' => ['id', 'name']]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Write launch copy',
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
        ]);
    }

    public function test_store_task_validates_required_fields(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/tasks'), ['status' => 'todo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority']);
    }

    public function test_store_task_rejects_invalid_priority(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/tasks'), $this->taskPayload(['priority' => 'asap']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    // ---------------------------------------------------------------
    // Show
    // ---------------------------------------------------------------

    public function test_show_task_returns_details(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
            'title' => 'Findable task',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/tasks/{$task->id}"))
            ->assertOk()
            ->assertJsonPath('title', 'Findable task');
    }

    public function test_show_missing_task_returns_404(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/tasks/999999'))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    public function test_update_task_persists_changes(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
            'status' => 'todo',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/tasks/{$task->id}"), [
                'title' => 'Renamed',
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Renamed')
            ->assertJsonPath('status', 'in_progress');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Renamed',
            'status' => 'in_progress',
        ]);
    }

    public function test_update_task_rejects_invalid_status(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/tasks/{$task->id}"), ['status' => 'done-done'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ---------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------

    public function test_delete_task_removes_it(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl("/tasks/{$task->id}"))
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_delete_missing_task_returns_404(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl('/tasks/999999'))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Cross-tenant writes (complements TenantIsolationTest reads)
    // ---------------------------------------------------------------

    public function test_user_cannot_update_another_tenants_task(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $userB->assignRole(Role::findByName('user', 'api'));

        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
        ]);

        $response = $this->actingAs($userB, 'sanctum')
            ->putJson($this->apiUrl("/tasks/{$task->id}"), ['title' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => $task->title]);
    }
}
