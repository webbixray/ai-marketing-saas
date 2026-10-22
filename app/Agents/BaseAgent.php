<?php

namespace App\Agents;

use App\Contracts\AgentContract;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

abstract class BaseAgent implements AgentContract
{
    protected array $config = [];
    protected AgentStatus $status = AgentStatus::INITIALIZING;
    protected float $lastExecutionTime = 0;
    protected int $executionCount = 0;
    protected int $errorCount = 0;

    /**
     * Get the unique identifier for this agent.
     */
    abstract public function getId(): string;

    /**
     * Get the human-readable name.
     */
    abstract public function getName(): string;

    /**
     * Get the agent's description/purpose.
     */
    abstract public function getDescription(): string;

    /**
     * Get the capabilities this agent provides.
     */
    abstract public function getCapabilities(): array;

    /**
     * Get the agent's configuration schema.
     */
    abstract public function getConfigSchema(): array;

    /**
     * Check if the agent can handle a given task.
     */
    public function canHandle(string $taskType, AgentContext $context): bool
    {
        $capabilities = $this->getCapabilities();
        return in_array($taskType, $capabilities) || in_array(AgentCapability::tryFrom($taskType)?->value, $capabilities);
    }

    /**
     * Execute the agent's primary logic.
     */
    public function execute(AgentContext $context): AgentResult
    {
        $startTime = microtime(true);
        $this->status = AgentStatus::RUNNING;
        $this->executionCount++;

        try {
            // Pre-execution hook
            $this->beforeExecute($context);

            // Execute the main logic
            $result = $this->performExecution($context);

            // Post-execution hook
            $this->afterExecute($context, $result);

            $this->status = AgentStatus::READY;
            $this->lastExecutionTime = microtime(true) - $startTime;

            // Record metrics
            $this->recordMetrics($context, $result, $this->lastExecutionTime);

            return $result;

        } catch (\Throwable $e) {
            $this->errorCount++;
            $this->status = AgentStatus::ERROR;
            
            Log::error("Agent {$this->getId()} execution failed", [
                'agent' => $this->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context' => $context->toArray(),
            ]);

            return AgentResult::failure($e->getMessage(), [
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
                'error_count' => $this->errorCount,
            ]);
        }
    }

    /**
     * Initialize the agent with configuration.
     */
    public function initialize(array $config): void
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->status = AgentStatus::READY;
        
        $this->onInitialize($config);
        
        Log::info("Agent {$this->getId()} initialized", [
            'agent' => $this->getId(),
            'config_keys' => array_keys($config),
        ]);
    }

    /**
     * Shutdown the agent gracefully.
     */
    public function shutdown(): void
    {
        $this->onShutdown();
        $this->status = AgentStatus::SHUTDOWN;
        
        Log::info("Agent {$this->getId()} shutdown", [
            'agent' => $this->getId(),
            'execution_count' => $this->executionCount,
            'error_count' => $this->errorCount,
        ]);
    }

    /**
     * Get the agent's current status.
     */
    public function getStatus(): AgentStatus
    {
        return $this->status;
    }

    /**
     * Validate agent configuration.
     */
    public function validateConfig(array $config): bool
    {
        $schema = $this->getConfigSchema();
        $required = array_filter($schema, fn($v) => $v['required'] ?? false);
        
        foreach ($required as $key => $rules) {
            if (!isset($config[$key])) {
                throw new \InvalidArgumentException("Required config key '{$key}' missing for agent {$this->getId()}");
            }
        }
        
        return true;
    }

    /**
     * Hook called before execution.
     */
    protected function beforeExecute(AgentContext $context): void
    {
        // Override in subclasses
    }

    /**
     * Hook called after execution.
     */
    protected function afterExecute(AgentContext $context, AgentResult $result): void
    {
        // Override in subclasses
    }

    /**
     * Main execution logic - must be implemented by subclasses.
     */
    abstract protected function performExecution(AgentContext $context): AgentResult;

    /**
     * Hook called during initialization.
     */
    protected function onInitialize(array $config): void
    {
        // Override in subclasses
    }

    /**
     * Hook called during shutdown.
     */
    protected function onShutdown(): void
    {
        // Override in subclasses
    }

    /**
     * Record execution metrics.
     */
    protected function recordMetrics(AgentContext $context, AgentResult $result, float $duration): void
    {
        $metrics = [
            'agent_id' => $this->getId(),
            'tenant_id' => $context->tenant->id,
            'duration_ms' => $duration * 1000,
            'success' => $result->success,
            'iterations' => $result->iterations,
            'timestamp' => now()->toISOString(),
        ];

        // Store in cache for real-time dashboards
        Cache::push("agent_metrics:{$this->getId()}", $metrics);
        
        // Keep only last 1000 entries
        $entries = Cache::get("agent_metrics:{$this->getId()}", []);
        if (count($entries) > 1000) {
            Cache::put("agent_metrics:{$this->getId()}", array_slice($entries, -1000));
        }
    }

    /**
     * Get agent statistics.
     */
    public function getStats(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'status' => $this->status->value,
            'execution_count' => $this->executionCount,
            'error_count' => $this->errorCount,
            'last_execution_ms' => $this->lastExecutionTime * 1000,
            'success_rate' => $this->executionCount > 0 
                ? round(($this->executionCount - $this->errorCount) / $this->executionCount * 100, 2)
                : 0,
            'capabilities' => $this->getCapabilities(),
        ];
    }
}
