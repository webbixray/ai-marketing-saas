<?php

namespace App\Domain\Agent;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class AgentContext
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?User $user = null,
        public readonly array $payload = [],
        public readonly Collection $sharedMemory = null,
        public readonly array $metadata = [],
        public readonly string $correlationId = '',
        public readonly int $maxIterations = 10,
        public readonly int $timeoutSeconds = 300,
    ) {
        $this->sharedMemory = $sharedMemory ?? new Collection();
        $this->correlationId = $correlationId ?: \Illuminate\Support\Str::uuid()->toString();
    }

    /**
     * Get a value from shared memory.
     */
    public function remember(string $key, mixed $value): void
    {
        $this->sharedMemory->put($key, $value);
    }

    /**
     * Retrieve a value from shared memory.
     */
    public function recall(string $key, mixed $default = null): mixed
    {
        return $this->sharedMemory->get($key, $default);
    }

    /**
     * Check if a key exists in shared memory.
     */
    public function has(string $key): bool
    {
        return $this->sharedMemory->has($key);
    }

    /**
     * Create a child context for sub-tasks.
     */
    public function createChild(array $additionalPayload = []): self
    {
        return new self(
            tenant: $this->tenant,
            user: $this->user,
            payload: array_merge($this->payload, $additionalPayload),
            sharedMemory: $this->sharedMemory,
            metadata: $this->metadata,
            correlationId: $this->correlationId,
            maxIterations: $this->maxIterations,
            timeoutSeconds: $this->timeoutSeconds,
        );
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user?->id,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
            'correlation_id' => $this->correlationId,
        ];
    }
}
