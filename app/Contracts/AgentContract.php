<?php

namespace App\Contracts;

use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;

interface AgentContract
{
    /**
     * Get the unique identifier for this agent.
     */
    public function getId(): string;

    /**
     * Get the human-readable name.
     */
    public function getName(): string;

    /**
     * Get the agent's description/purpose.
     */
    public function getDescription(): string;

    /**
     * Get the capabilities this agent provides.
     */
    public function getCapabilities(): array;

    /**
     * Check if the agent can handle a given task.
     */
    public function canHandle(string $taskType, AgentContext $context): bool;

    /**
     * Execute the agent's primary logic.
     */
    public function execute(AgentContext $context): AgentResult;

    /**
     * Get the agent's configuration schema.
     */
    public function getConfigSchema(): array;

    /**
     * Validate agent configuration.
     */
    public function validateConfig(array $config): bool;

    /**
     * Get the agent's current status.
     */
    public function getStatus(): AgentStatus;

    /**
     * Initialize the agent with configuration.
     */
    public function initialize(array $config): void;

    /**
     * Shutdown the agent gracefully.
     */
    public function shutdown(): void;
}

enum AgentStatus: string
{
    case INITIALIZING = 'initializing';
    case READY = 'ready';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case ERROR = 'error';
    case SHUTDOWN = 'shutdown';
}
