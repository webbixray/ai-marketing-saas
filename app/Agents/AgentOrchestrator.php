<?php

namespace App\Agents;

use App\Contracts\AgentContract;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AgentOrchestrator
{
    protected Collection $agents;
    protected Collection $agentRegistry;
    protected array $executionHistory = [];
    protected int $maxConcurrentAgents = 5;
    protected int $defaultTimeout = 300;

    public function __construct()
    {
        $this->agents = new Collection();
        $this->agentRegistry = new Collection();
    }

    /**
     * Register an agent.
     */
    public function registerAgent(AgentContract $agent, array $config = []): self
    {
        $agent->initialize($config);
        $this->agents->put($agent->getId(), $agent);
        $this->agentRegistry->put($agent->getId(), [
            'agent' => $agent,
            'config' => $config,
            'registered_at' => now()->toISOString(),
        ]);

        Log::info("Agent registered", ['agent_id' => $agent->getId(), 'name' => $agent->getName()]);

        return $this;
    }

    /**
     * Unregister an agent.
     */
    public function unregisterAgent(string $agentId): bool
    {
        if ($this->agents->has($agentId)) {
            $agent = $this->agents->get($agentId);
            $agent->shutdown();
            $this->agents->forget($agentId);
            $this->agentRegistry->forget($agentId);
            return true;
        }
        return false;
    }

    /**
     * Get an agent by ID.
     */
    public function getAgent(string $agentId): ?AgentContract
    {
        return $this->agents->get($agentId);
    }

    /**
     * Get all registered agents.
     */
    public function getAllAgents(): Collection
    {
        return $this->agents;
    }

    /**
     * Find agents that can handle a specific task type.
     */
    public function findAgentsForTask(string $taskType, AgentContext $context): Collection
    {
        return $this->agents->filter(function ($agent) use ($taskType, $context) {
            return $agent->canHandle($taskType, $context) 
                && $agent->getStatus() === AgentStatus::READY;
        });
    }

    /**
     * Execute a single agent.
     */
    public function executeAgent(string $agentId, AgentContext $context): AgentResult
    {
        $agent = $this->getAgent($agentId);
        
        if (!$agent) {
            return AgentResult::failure("Agent {$agentId} not found");
        }

        if ($agent->getStatus() !== AgentStatus::READY) {
            return AgentResult::failure("Agent {$agentId} is not ready (status: {$agent->getStatus()->value})");
        }

        Log::info("Executing agent", [
            'agent_id' => $agentId,
            'correlation_id' => $context->correlationId,
        ]);

        return $agent->execute($context);
    }

    /**
     * Execute multiple agents in parallel.
     */
    public function executeParallel(array $agentIds, AgentContext $context): array
    {
        $results = [];
        
        foreach ($agentIds as $agentId) {
            $agent = $this->getAgent($agentId);
            if ($agent && $agent->getStatus() === AgentStatus::READY) {
                $results[$agentId] = $agent->execute($context->createChild());
            } else {
                $results[$agentId] = AgentResult::failure("Agent {$agentId} not available");
            }
        }

        return $results;
    }

    /**
     * Execute agents sequentially (pipeline).
     */
    public function executePipeline(array $agentIds, AgentContext $context): array
    {
        $results = [];
        $currentContext = $context;

        foreach ($agentIds as $agentId) {
            $result = $this->executeAgent($agentId, $currentContext);
            $results[$agentId] = $result;

            // Pass result data to next agent
            if ($result->success && $result->data !== null) {
                $currentContext = $currentContext->createChild([
                    'previous_result' => $result->data,
                    'previous_agent' => $agentId,
                ]);
            }

            // Stop pipeline on failure if configured
            if (!$result->success) {
                Log::warning("Pipeline stopped due to agent failure", [
                    'failed_agent' => $agentId,
                    'error' => $result->error,
                ]);
                break;
            }
        }

        return $results;
    }

    /**
     * Execute a workflow with conditional branching.
     */
    public function executeWorkflow(string $workflowId, AgentContext $context): AgentResult
    {
        $workflow = $this->getWorkflow($workflowId);
        
        if (!$workflow) {
            return AgentResult::failure("Workflow {$workflowId} not found");
        }

        return $this->executeWorkflowSteps($workflow['steps'], $context);
    }

    /**
     * Execute workflow steps with conditional logic.
     */
    protected function executeWorkflowSteps(array $steps, AgentContext $context): AgentResult
    {
        $results = [];
        $currentContext = $context;

        foreach ($steps as $step) {
            // Check condition if present
            if (isset($step['condition'])) {
                $shouldExecute = $this->evaluateCondition($step['condition'], $currentContext);
                if (!$shouldExecute) {
                    Log::info("Workflow step skipped", ['step' => $step['id'], 'reason' => 'condition_not_met']);
                    continue;
                }
            }

            $agentId = $step['agent'];
            $result = $this->executeAgent($agentId, $currentContext);
            $results[$step['id']] = $result;

            if (!$result->success) {
                // Handle failure based on step config
                if ($step['on_failure'] ?? 'stop') === 'stop') {
                    return AgentResult::failure(
                        "Workflow step {$step['id']} failed: {$result->error}",
                        ['results' => $results]
                    );
                }
            }

            // Update context with result
            if ($result->success && $result->data !== null) {
                $currentContext = $currentContext->createChild([
                    "step_{$step['id']}_result" => $result->data,
                ]);
            }
        }

        return AgentResult::success($results, [
            'steps_executed' => count($results),
            'workflow_completed' => true,
        ]);
    }

    /**
     * Evaluate a workflow condition.
     */
    protected function evaluateCondition(array $condition, AgentContext $context): bool
    {
        // Simple condition evaluation - can be extended
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;

        $actualValue = data_get($context->toArray(), $field);

        return match ($operator) {
            'eq' => $actualValue == $value,
            'neq' => $actualValue != $value,
            'gt' => $actualValue > $value,
            'gte' => $actualValue >= $value,
            'lt' => $actualValue < $value,
            'lte' => $actualValue <= $value,
            'in' => in_array($actualValue, (array)$value),
            'not_in' => !in_array($actualValue, (array)$value),
            'contains' => is_string($actualValue) && str_contains($actualValue, $value),
            default => false,
        };
    }

    /**
     * Register a workflow.
     */
    public function registerWorkflow(string $workflowId, array $workflow): self
    {
        Cache::put("workflow:{$workflowId}", $workflow);
        return $this;
    }

    /**
     * Get a workflow.
     */
    protected function getWorkflow(string $workflowId): ?array
    {
        return Cache::get("workflow:{$workflowId}");
    }

    /**
     * Get orchestrator statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'total_agents' => $this->agents->count(),
            'agents_by_status' => [],
            'agent_details' => [],
        ];

        foreach ($this->agents as $agent) {
            $status = $agent->getStatus()->value;
            $stats['agents_by_status'][$status] = ($stats['agents_by_status'][$status] ?? 0) + 1;
            $stats['agent_details'][] = $agent->getStats();
        }

        return $stats;
    }

    /**
     * Health check all agents.
     */
    public function healthCheck(): array
    {
        $health = [
            'healthy' => true,
            'agents' => [],
        ];

        foreach ($this->agents as $id => $agent) {
            $agentHealth = [
                'id' => $id,
                'name' => $agent->getName(),
                'status' => $agent->getStatus()->value,
                'healthy' => $agent->getStatus() === AgentStatus::READY,
                'stats' => $agent->getStats(),
            ];

            if (!$health['agents'][$id]['healthy']) {
                $health['healthy'] = false;
            }

            $health['agents'][] = $agentHealth;
        }

        return $health;
    }

    /**
     * Shutdown all agents.
     */
    public function shutdownAll(): void
    {
        foreach ($this->agents as $agent) {
            $agent->shutdown();
        }
        $this->agents = new Collection();
        $this->agentRegistry = new Collection();
    }
}
