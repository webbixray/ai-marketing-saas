<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AI\LLMService;
use App\Agents\AI\CampaignBuilderAgent;
use App\Agents\AI\CopyGenerationAgent;
use App\Agents\AI\CreativeAssetAgent;
use App\Agents\AI\ChatAnalyticsAgent;
use App\Agents\Intelligence\CampaignOptimizationAgent;
use App\Agents\Intelligence\ClientIntelligenceAgent;
use App\Agents\Intelligence\CreativeIntelligenceAgent;
use App\Agents\AgentOrchestrator;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LLMService::class, function ($app) {
            return new LLMService(config('ai.llm', []));
        });

        $this->app->singleton(AgentOrchestrator::class, function ($app) {
            $orchestrator = new AgentOrchestrator();

            // Register intelligence agents
            $orchestrator->registerAgent(
                $app->make(CampaignOptimizationAgent::class),
                config('ai.agents.campaign_optimization', [])
            );

            $orchestrator->registerAgent(
                $app->make(ClientIntelligenceAgent::class),
                config('ai.agents.client_intelligence', [])
            );

            $orchestrator->registerAgent(
                $app->make(CreativeIntelligenceAgent::class),
                config('ai.agents.creative_intelligence', [])
            );

            // Register AI agents
            $orchestrator->registerAgent(
                $app->make(CampaignBuilderAgent::class),
                config('ai.agents.campaign_builder', [])
            );

            $orchestrator->registerAgent(
                $app->make(CopyGenerationAgent::class),
                config('ai.agents.copy_generation', [])
            );

            $orchestrator->registerAgent(
                $app->make(CreativeAssetAgent::class),
                config('ai.agents.creative_asset', [])
            );

            $orchestrator->registerAgent(
                $app->make(ChatAnalyticsAgent::class),
                config('ai.agents.chat_analytics', [])
            );

            return $orchestrator;
        });

        // Register individual agents
        $this->app->singleton(CampaignBuilderAgent::class, function ($app) {
            return new CampaignBuilderAgent($app->make(LLMService::class));
        });

        $this->app->singleton(CopyGenerationAgent::class, function ($app) {
            return new CopyGenerationAgent($app->make(LLMService::class));
        });

        $this->app->singleton(CreativeAssetAgent::class, function ($app) {
            return new CreativeAssetAgent($app->make(LLMService::class));
        });

        $this->app->singleton(ChatAnalyticsAgent::class, function ($app) {
            return new ChatAnalyticsAgent($app->make(LLMService::class));
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/ai.php' => config_path('ai.php'),
        ], 'ai-config');
    }
}
