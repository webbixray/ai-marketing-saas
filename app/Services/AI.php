<?php

namespace App\Services;

use Illuminate\Support\ServiceProvider;
use App\Services\AI\LLMService;
use App\Agents\AI\CampaignBuilderAgent;
use App\Agents\AI\CopyGenerationAgent;
use App\Agents\AI\CreativeAssetAgent;
use App\Agents\AI\AgentOrchestrator;

class AIService extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('AI', function () {
            return new LLMService();
        });

        $this->app->bind(CampaignBuilderAgent::class, function () {
            return new CampaignBuilderAgent();
        });

        $this->app->bind(CopyGenerationAgent::class, function () {
            return new CopyGenerationAgent();
        });

        $this->app->bind(CreativeAssetAgent::class, function () {
            return new CreativeAssetAgent();
        });

        $this->app->bind(AgentOrchestrator::class, function () {
            return new AgentOrchestrator();
        });
    }

    public function boot()
    {
        //
    }
}