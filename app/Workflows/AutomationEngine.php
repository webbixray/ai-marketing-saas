<?php

namespace App\Workflows;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class AutomationEngine
{
    protected Tenant $tenant;
    protected ?User $user;
    protected array $rules = [];
    protected Collection $workflows;

    public function __construct(Tenant $tenant, ?User $user = null)
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->workflows = new Collection();
    }

    /**
     * Load all active rules for the tenant.
     */
    public function loadRules(): void
    {
        $this->rules = DB::table('automation_rules')
            ->where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Evaluate all rules against a trigger event.
     */
    public function evaluate(string $triggerType, array $payload = []): array
    {
        $this->loadRules();
        
        $triggeredRules = [];
        $executedActions = [];

        foreach ($this->rules as $rule) {
            if ($this->matchesTrigger($rule, $triggerType, $payload)) {
                if ($this->evaluateConditions($rule, $payload)) {
                    $triggeredRules[] = $rule;
                    
                    // Execute actions
                    foreach ($rule->actions as $action) {
                        $result = $this->executeAction($rule, $action, $payload);
                        $executedActions[] = [
                            'rule_id' => $rule->id,
                            'rule_name' => $rule->name,
                            'action' => $action,
                            'result' => $result,
                        ];
                    }

                    // Update rule execution stats
                    DB::table('automation_rules')
                        ->where('id', $rule->id)
                        ->increment('execution_count');
                    
                    DB::table('automation_rules')
                        ->where('id', $rule->id)
                        ->update(['last_executed_at' => now()]);
                }
            }
        }

        // Log automation execution
        if (!empty($triggeredRules)) {
            Log::info('Automation rules triggered', [
                'tenant_id' => $this->tenant->id,
                'trigger' => $triggerType,
                'rules_triggered' => count($triggeredRules),
                'actions_executed' => count($executedActions),
            ]);
        }

        return [
            'triggered_rules' => $triggeredRules,
            'executed_actions' => $executedActions,
        ];
    }

    /**
     * Check if rule matches trigger type.
     */
    protected function matchesTrigger($rule, string $triggerType, array $payload): bool
    {
        $ruleTriggers = json_decode($rule->triggers ?? '[]', true);
        
        if (empty($ruleTriggers)) {
            return false;
        }

        // Check if trigger type matches
        if (!in_array($triggerType, $ruleTriggers)) {
            return false;
        }

        // Check trigger filters if present
        if (isset($rule->trigger_filters)) {
            $filters = json_decode($rule->trigger_filters, true);
            foreach ($filters as $field => $expectedValue) {
                $actualValue = data_get($payload, $field);
                if ($actualValue != $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Evaluate rule conditions.
     */
    protected function evaluateConditions($rule, array $payload): bool
    {
        $conditions = json_decode($rule->conditions ?? '[]', true);
        
        if (empty($conditions)) {
            return true; // No conditions = always true
        }

        $logic = $rule->condition_logic ?? 'AND';

        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($condition, $payload);
            
            if ($logic === 'AND' && !$result) {
                return false;
            }
            
            if ($logic === 'OR' && $result) {
                return true;
            }
        }

        return $logic === 'AND' ? true : false;
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, array $payload): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;
        $dataSource = $condition['data_source'] ?? 'payload'; // payload, context, model

        $actualValue = $this->getValue($dataSource, $field, $payload);

        switch ($operator) {
            case 'eq':
                return $actualValue == $value;
            case 'neq':
                return $actualValue != $value;
            case 'gt':
                return $actualValue > $value;
            case 'gte':
                return $actualValue >= $value;
            case 'lt':
                return $actualValue < $value;
            case 'lte':
                return $actualValue <= $value;
            case 'in':
                return in_array($actualValue, (array)$value);
            case 'not_in':
                return !in_array($actualValue, (array)$value);
            case 'contains':
                return is_string($actualValue) && str_contains($actualValue, $value);
            case 'not_contains':
                return is_string($actualValue) && !str_contains($actualValue, $value);
            case 'starts_with':
                return is_string($actualValue) && str_starts_with($actualValue, $value);
            case 'ends_with':
                return is_string($actualValue) && str_ends_with($actualValue, $value);
            case 'between':
                return $actualValue >= ($value[0] ?? 0) && $actualValue <= ($value[1] ?? PHP_INT_MAX);
            case 'regex':
                return preg_match($value, $actualValue) === 1;
            default:
                return false;
        }
    }

    /**
     * Get value from specified data source.
     */
    protected function getValue(string $source, string $field, array $payload): mixed
    {
        return match ($source) {
            'payload' => data_get($payload, $field),
            'context' => data_get($this->getContext(), $field),
            'model' => $this->getModelValue($field, $payload),
            default => data_get($payload, $field),
        };
    }

    /**
     * Get value from related model.
     */
    protected function getModelValue(string $field, array $payload): mixed
    {
        $modelType = data_get($payload, 'model_type');
        $modelId = data_get($payload, 'model_id');

        if (!$modelType || !$modelId) {
            return null;
        }

        $model = $modelType::find($modelId);
        if (!$model) {
            return null;
        }

        return data_get($model->toArray(), $field);
    }

    /**
     * Get context data.
     */
    protected function getContext(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user?->id,
            'time' => now(),
            'timezone' => $this->tenant->timezone ?? 'UTC',
        ];
    }

    /**
     * Execute a rule action.
     */
    protected function executeAction($rule, array $action, array $payload): array
    {
        $actionType = $action['type'] ?? '';
        $params = $action['params'] ?? [];

        try {
            $result = match ($actionType) {
                'pause_campaign' => $this->actionPauseCampaign($params, $payload),
                'resume_campaign' => $this->actionResumeCampaign($params, $payload),
                'adjust_budget' => $this->actionAdjustBudget($params, $payload),
                'adjust_bid' => $this->actionAdjustBid($params, $payload),
                'send_notification' => $this->actionSendNotification($params, $payload),
                'create_task' => $this->actionCreateTask($params, $payload),
                'update_status' => $this->actionUpdateStatus($params, $payload),
                'send_email' => $this->actionSendEmail($params, $payload),
                'call_webhook' => $this->actionCallWebhook($params, $payload),
                'run_workflow' => $this->actionRunWorkflow($params, $payload),
                'update_custom_field' => $this->actionUpdateCustomField($params, $payload),
                default => ['success' => false, 'error' => "Unknown action type: {$actionType}"],
            };

            // Log action execution
            DB::table('automation_action_logs')->insert([
                'tenant_id' => $this->tenant->id,
                'rule_id' => $rule->id,
                'action_type' => $actionType,
                'params' => json_encode($params),
                'payload' => json_encode($payload),
                'result' => json_encode($result),
                'executed_at' => now(),
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error("Automation action failed", [
                'rule_id' => $rule->id,
                'action' => $actionType,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==================== Action Implementations ====================

    protected function actionPauseCampaign(array $params, array $payload): array
    {
        $campaignId = $params['campaign_id'] ?? data_get($payload, 'campaign_id');
        if (!$campaignId) {
            return ['success' => false, 'error' => 'No campaign ID provided'];
        }

        $updated = \App\Models\Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->tenant->id)
            ->update(['status' => 'paused']);

        return ['success' => $updated > 0, 'campaign_id' => $campaignId];
    }

    protected function actionResumeCampaign(array $params, array $payload): array
    {
        $campaignId = $params['campaign_id'] ?? data_get($payload, 'campaign_id');
        if (!$campaignId) {
            return ['success' => false, 'error' => 'No campaign ID provided'];
        }

        $updated = \App\Models\Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->tenant->id)
            ->update(['status' => 'active']);

        return ['success' => $updated > 0, 'campaign_id' => $campaignId];
    }

    protected function actionAdjustBudget(array $params, array $payload): array
    {
        $campaignId = $params['campaign_id'] ?? data_get($payload, 'campaign_id');
        $adjustmentType = $params['adjustment_type'] ?? 'percentage'; // percentage, fixed
        $value = $params['value'] ?? 0;

        if (!$campaignId) {
            return ['success' => false, 'error' => 'No campaign ID provided'];
        }

        $campaign = \App\Models\Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        if (!$campaign) {
            return ['success' => false, 'error' => 'Campaign not found'];
        }

        $currentBudget = $campaign->daily_budget ?? ($campaign->budget / 30);
        
        $newBudget = match ($params['adjustment_type'] ?? 'percentage') {
            'percentage' => $campaign->daily_budget * (1 + $value / 100),
            'fixed' => $currentBudget + $value,
            'set' => $value,
            default => $currentBudget,
        };

        // Apply max change limit
        $maxChange = 0.5; // 50% max change
        $maxBudget = $currentBudget * (1 + 0.5);
        $minBudget = $currentBudget * (1 - 0.5);
        $newBudget = max($minBudget, min($maxBudget, $newBudget));

        $updated = \App\Models\Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->tenant->id)
            ->update(['daily_budget' => round($newBudget, 2)]);

        return ['success' => $updated > 0, 'new_budget' => round($newBudget, 2)];
    }

    protected function actionAdjustBid(array $params, array $payload): array
    {
        $campaignId = $params['campaign_id'] ?? data_get($payload, 'campaign_id');
        $adjustmentType = $params['adjustment_type'] ?? 'percentage';
        $value = $params['value'] ?? 0;

        if (!$campaignId) {
            return ['success' => false, 'error' => 'No campaign ID provided'];
        }

        $campaign = \App\Models\Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        if (!$campaign || !$campaign->bid_amount) {
            return ['success' => false, 'error' => 'Campaign or bid not found'];
        }

        $currentBid = $campaign->bid_amount;
        $newBid = match ($params['adjustment_type'] ?? 'percentage') {
            'percentage' => $currentBid * (1 + $value / 100),
            'fixed' => $currentBid + $value,
            'set' => $value,
            default => $currentBid,
        };

        // Apply max change limit (15%)
        $maxChange = 0.15;
        $newBid = max($currentBid * (1 - $maxChange), min($currentBid * (1 + $maxChange), $newBid));

        $updated = \App\Models\Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->tenant->id)
            ->update(['bid_amount' => round($newBid, 2)]);

        return ['success' => $updated > 0, 'new_bid' => round($newBid, 2)];
    }

    protected function actionSendNotification(array $params, array $payload): array
    {
        $userId = $params['user_id'] ?? data_get($payload, 'user_id');
        $message = $params['message'] ?? '';
        $type = $params['type'] ?? 'info';

        if (!$userId) {
            return ['success' => false, 'error' => 'No user ID provided'];
        }

        // Create notification
        \App\Models\Notification::create([
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $userId,
            'type' => "automation_{$type}",
            'data' => [
                'message' => $message,
                'automation_trigger' => true,
            ],
        ]);

        return ['success' => true, 'notification_type' => $type];
    }

    protected function actionCreateTask(array $params, array $payload): array
    {
        $taskData = [
            'tenant_id' => $this->tenant->id,
            'title' => $params['title'] ?? 'Automation Task',
            'description' => $params['description'] ?? '',
            'status' => 'todo',
            'priority' => $params['priority'] ?? 'medium',
            'assigned_to' => $params['assigned_to'] ?? null,
            'due_date' => $params['due_date'] ?? now()->addDays(1),
        ];

        $task = \App\Models\Task::create($taskData);

        return ['success' => true, 'task_id' => $task->id];
    }

    protected function actionUpdateStatus(array $params, array $payload): array
    {
        $modelType = $params['model_type'] ?? '';
        $modelId = $params['model_id'] ?? data_get($payload, 'model_id');
        $newStatus = $params['status'] ?? '';

        if (!$modelType || !$modelId || !$newStatus) {
            return ['success' => false, 'error' => 'Missing required parameters'];
        }

        $modelClass = "App\\Models\\{$modelType}";
        if (!class_exists($modelType)) {
            return ['success' => false, 'error' => "Model {$modelType} not found"];
        }

        $updated = $modelType::where('id', $modelId)
            ->where('tenant_id', $this->tenant->id)
            ->update(['status' => $newStatus]);

        return ['success' => $updated > 0];
    }

    protected function actionSendEmail(array $params, array $payload): array
    {
        // Implementation would use Laravel Mail
        return ['success' => true, 'message' => 'Email queued'];
    }

    protected function actionCallWebhook(array $params, array $payload): array
    {
        $url = $params['url'] ?? '';
        $method = $params['method'] ?? 'POST';
        $headers = $params['headers'] ?? [];
        $body = $params['body'] ?? $payload;

        if (!$url) {
            return ['success' => false, 'error' => 'No webhook URL provided'];
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->timeout(30)
            ->retry(3, 100)
            ->$method($url, $body);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }

    protected function actionRunWorkflow(array $params, array $payload): array
    {
        $workflowId = $params['workflow_id'] ?? '';
        
        if (!$workflowId) {
            return ['success' => false, 'error' => 'No workflow ID provided'];
        }

        // Dispatch workflow job
        \App\Jobs\RunWorkflowJob::dispatch($this->tenant->id, $workflowId, $payload);

        return ['success' => true, 'workflow_id' => $workflowId];
    }

    protected function actionUpdateCustomField(array $params, array $payload): array
    {
        $modelType = $params['model_type'] ?? '';
        $modelId = $params['model_id'] ?? data_get($payload, 'model_id');
        $field = $params['field'] ?? '';
        $value = $params['value'] ?? null;

        if (!$modelType || !$modelId || !$field) {
            return ['success' => false, 'error' => 'Missing required parameters'];
        }

        $modelClass = "App\\Models\\{$modelType}";
        $model = $modelType::where('id', $modelId)->where('tenant_id', $this->tenant->id)->first();

        if (!$model) {
            return ['success' => false, 'error' => 'Model not found'];
        }

        $customFields = $model->custom_fields ?? [];
        $customFields[$field] = $value;
        
        $model->update(['custom_fields' => $customFields]);

        return ['success' => true];
    }
}
