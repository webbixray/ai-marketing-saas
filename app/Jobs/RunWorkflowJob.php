<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Tenant;
use App\Agents\AgentOrchestrator;
use App\Domain\Agent\AgentContext;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RunWorkflowJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $tenantId,
        public string $workflowId,
        public array $payload = []
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $user = User::where('tenant_id', $this->tenantId)->first();

        $orchestrator = new \App\Agents\AgentOrchestrator();
        
        // Register all available agents
        $orchestrator->registerAgent(new \App\Agents\Intelligence\CampaignOptimizationAgent(), []);
        $orchestrator->registerAgent(new \App\Agents\Intelligence\ClientIntelligenceAgent(), []);
        $orchestrator->registerAgent(new \App\Agents\Intelligence\CreativeIntelligenceAgent(), []);

        $context = new \App\Domain\Agent\AgentContext($tenant, $user, $this->payload);

        try {
            $result = $orchestrator->executeWorkflow($this->workflowId, $context);
            
            Log::info("Workflow {$this->workflowId} completed", [
                'tenant_id' => $this->tenantId,
                'workflow_id' => $this->workflowId,
                'success' => $result->success,
            ]);
        } catch (\Throwable $e) {
            Log::error("Workflow {$this->workflowId} failed", [
                'tenant_id' => $this->tenantId,
                'workflow_id' => $this->workflowId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
