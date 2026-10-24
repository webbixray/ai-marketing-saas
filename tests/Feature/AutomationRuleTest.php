<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Automation Rules module: full CRUD via /api/v1/automation-rules,
 * validation, and cross-tenant isolation.
 */
class AutomationRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Notify on new campaign',
            'description' => 'Sends an email when a campaign is created',
            'triggers' => ['campaign.created'],
            'conditions' => [[
                'field' => 'status',
                'operator' => 'equals',
                'value' => 'active',
            ]],
            'condition_logic' => 'AND',
            'actions' => [[
                'type' => 'notify',
                'params' => ['channel' => 'email'],
            ]],
            'priority' => 10,
        ], $overrides);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson($this->apiUrl('/automation-rules'))
            ->assertUnauthorized();
    }

    public function test_index_lists_rules_scoped_to_own_tenant(): void
    {
        AutomationRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rule One']);
        AutomationRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rule Two']);
        $other = Tenant::factory()->create();
        AutomationRule::factory()->create(['tenant_id' => $other->id, 'name' => 'Foreign Rule']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/automation-rules'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $names = array_column($response->json('data'), 'name');
        sort($names);
        $this->assertSame(['Rule One', 'Rule Two'], $names);
    }

    public function test_index_filters_by_is_active_and_search(): void
    {
        AutomationRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Churn Alert']);
        AutomationRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Budget Check']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/automation-rules?search=Churn'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Churn Alert');

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/automation-rules?is_active=true'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_rule_returns_201_and_persists_tenant(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/automation-rules'), $this->rulePayload());

        $response->assertCreated()
            ->assertJsonPath('name', 'Notify on new campaign')
            ->assertJsonPath('tenant_id', $this->tenant->id)
            ->assertJsonPath('created_by', $this->user->id)
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('automation_rules', [
            'name' => 'Notify on new campaign',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_rule_validates_required_fields(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/automation-rules'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'triggers', 'conditions', 'condition_logic', 'actions']);
    }

    public function test_store_rule_rejects_empty_triggers_and_invalid_logic(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/automation-rules'), $this->rulePayload([
                'triggers' => [],
                'condition_logic' => 'BOTH',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['triggers', 'condition_logic']);
    }

    public function test_show_rule_returns_it(): void
    {
        $rule = AutomationRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Showable Rule',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/automation-rules/{$rule->id}"))
            ->assertOk()
            ->assertJsonPath('name', 'Showable Rule')
            ->assertJsonPath('creator.id', $this->user->id);
    }

    public function test_show_missing_rule_returns_404(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/automation-rules/999999'))
            ->assertNotFound();
    }

    public function test_update_rule_persists_changes_and_sets_updater(): void
    {
        $rule = AutomationRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Before Update',
            'is_active' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/automation-rules/{$rule->id}"), [
                'name' => 'After Update',
                'is_active' => false,
                'priority' => 99,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'After Update')
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('priority', 99);

        $this->assertDatabaseHas('automation_rules', [
            'id' => $rule->id,
            'name' => 'After Update',
            'is_active' => false,
            'updated_by' => $this->user->id,
        ]);
    }

    public function test_update_rule_validates_fields(): void
    {
        $rule = AutomationRule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/automation-rules/{$rule->id}"), [
                'conditions' => 'not-an-array',
                'condition_logic' => 'WHILE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['conditions', 'condition_logic']);
    }

    public function test_delete_rule_soft_deletes_it(): void
    {
        $rule = AutomationRule::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl("/automation-rules/{$rule->id}"))
            ->assertNoContent();

        $this->assertSoftDeleted('automation_rules', ['id' => $rule->id]);
    }

    public function test_cross_tenant_user_cannot_read_foreign_rule(): void
    {
        $rule = AutomationRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tenant A Rule']);
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id]);

        $this->actingAs($foreignUser, 'sanctum')
            ->getJson($this->apiUrl("/automation-rules/{$rule->id}"))
            ->assertNotFound();

        $this->actingAs($foreignUser, 'sanctum')
            ->getJson($this->apiUrl('/automation-rules'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cross_tenant_user_cannot_update_foreign_rule(): void
    {
        $rule = AutomationRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Untouchable']);
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id]);

        $this->actingAs($foreignUser, 'sanctum')
            ->putJson($this->apiUrl("/automation-rules/{$rule->id}"), ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('automation_rules', ['id' => $rule->id, 'name' => 'Untouchable']);
    }

    public function test_cross_tenant_user_cannot_delete_foreign_rule(): void
    {
        $rule = AutomationRule::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id]);

        $this->actingAs($foreignUser, 'sanctum')
            ->deleteJson($this->apiUrl("/automation-rules/{$rule->id}"))
            ->assertNotFound();

        $this->assertDatabaseHas('automation_rules', ['id' => $rule->id, 'deleted_at' => null]);
    }
}
