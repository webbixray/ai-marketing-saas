<?php

namespace App\Domain\Agent;

use Illuminate\Support\Collection;

class AgentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly Collection $artifacts = null,
        public readonly array $metrics = [],
        public readonly array $nextActions = [],
        public readonly float $durationMs = 0,
        public readonly int $iterations = 0,
    ) {
        $this->artifacts = $artifacts ?? new Collection();
    }

    /**
     * Create a successful result.
     */
    public static function success(mixed $data = null, array $metrics = [], array $nextActions = []): self
    {
        return new self(true, $data, null, new Collection(), $metrics, $nextActions);
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $error, array $metrics = []): self
    {
        return new self(false, null, $error, new Collection(), $metrics);
    }

    /**
     * Add an artifact to the result.
     */
    public function withArtifact(string $key, mixed $value): self
    {
        $artifacts = $this->artifacts->put($key, $value);
        return new self(
            $this->success,
            $this->data,
            $this->error,
            $artifacts,
            $this->metrics,
            $this->nextActions,
            $this->durationMs,
            $this->iterations,
        );
    }

    /**
     * Add a metric to the result.
     */
    public function withMetric(string $key, mixed $value): self
    {
        $metrics = array_merge($this->metrics, [$key => $value]);
        return new self(
            $this->success,
            $this->data,
            $this->error,
            $this->artifacts,
            $metrics,
            $this->nextActions,
            $this->durationMs,
            $this->iterations,
        );
    }

    /**
     * Add a next action suggestion.
     */
    public function withNextAction(string $actionType, array $payload = [], int $priority = 0): self
    {
        $nextActions = array_merge($this->nextActions, [[
            'type' => $actionType,
            'payload' => $payload,
            'priority' => $priority,
            'created_at' => now()->toISOString(),
        ]]);
        return new self(
            $this->success,
            $this->data,
            $this->error,
            $this->artifacts,
            $this->metrics,
            $nextActions,
            $this->durationMs,
            $this->iterations,
        );
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'artifacts' => $this->artifacts->toArray(),
            'metrics' => $this->metrics,
            'next_actions' => $this->nextActions,
            'duration_ms' => $this->durationMs,
            'iterations' => $this->iterations,
        ];
    }
}
