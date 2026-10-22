<?php

namespace App\Domain\Agent;

enum AgentCapability: string
{
    // Campaign capabilities
    case CAMPAIGN_CREATE = 'campaign.create';
    case CAMPAIGN_OPTIMIZE = 'campaign.optimize';
    case CAMPAIGN_ANALYZE = 'campaign.analyze';
    case CAMPAIGN_BUDGET_PACE = 'campaign.budget_pace';
    case CAMPAIGN_CREATIVE_TEST = 'campaign.creative_test';
    case CAMPAIGN_AUDIENCE_EXPAND = 'campaign.audience_expand';
    
    // Client capabilities
    case CLIENT_HEALTH_SCORE = 'client.health_score';
    case CLIENT_CHURN_PREDICT = 'client.churn_predict';
    case CLIENT_UPSELL_IDENTIFY = 'client.upsell_identify';
    case CLIENT_REPORT_GENERATE = 'client.report_generate';
    
    // Creative capabilities
    case CREATIVE_PERFORMANCE_SCORE = 'creative.performance_score';
    case CREATIVE_FATIGUE_DETECT = 'creative.fatigue_detect';
    case CREATIVE_VARIANT_GENERATE = 'creative.variant_generate';
    case CREATIVE_ASSET_MANAGE = 'creative.asset_manage';
    
    // Analytics capabilities
    case ATTRIBUTION_CALCULATE = 'attribution.calculate';
    case INCREMENTALITY_TEST = 'incrementality.test';
    case FORECAST_PREDICT = 'forecast.predict';
    case ANOMALY_DETECT = 'anomaly.detect';
    
    // Automation capabilities
    case RULE_EVALUATE = 'rule.evaluate';
    case WORKFLOW_EXECUTE = 'workflow.execute';
    case TRIGGER_ACTION = 'trigger.action';
    case SCHEDULE_TASK = 'schedule.task';
    
    // Integration capabilities
    case META_API_SYNC = 'integration.meta.sync';
    case GOOGLE_API_SYNC = 'integration.google.sync';
    case LINKEDIN_API_SYNC = 'integration.linkedin.sync';
    case TIKTOK_API_SYNC = 'integration.tiktok.sync';
    case GA4_API_SYNC = 'integration.ga4.sync';
    
    // Intelligence capabilities
    case PATTERN_RECOGNIZE = 'intelligence.pattern_recognize';
    case RECOMMENDATION_GENERATE = 'intelligence.recommendation_generate';
    case DECISION_MAKE = 'intelligence.decision_make';
    case LEARNING_UPDATE = 'intelligence.learning_update';

    /**
     * Get the category for this capability.
     */
    public function category(): string
    {
        return match (true) {
            str_starts_with($this->value, 'campaign.') => 'campaign',
            str_starts_with($this->value, 'client.') => 'client',
            str_starts_with($this->value, 'creative.') => 'creative',
            str_starts_with($this->value, 'attribution.') => 'analytics',
            str_starts_with($this->value, 'incrementality.') => 'analytics',
            str_starts_with($this->value, 'forecast.') => 'analytics',
            str_starts_with($this->value, 'anomaly.') => 'analytics',
            str_starts_with($this->value, 'rule.') => 'automation',
            str_starts_with($this->value, 'workflow.') => 'automation',
            str_starts_with($this->value, 'trigger.') => 'automation',
            str_starts_with($this->value, 'schedule.') => 'automation',
            str_starts_with($this->value, 'integration.') => 'integration',
            str_starts_with($this->value, 'intelligence.') => 'intelligence',
            default => 'general',
        };
    }

    /**
     * Get human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::CAMPAIGN_CREATE => 'Create new marketing campaigns',
            self::CAMPAIGN_OPTIMIZE => 'Optimize existing campaigns for better performance',
            self::CAMPAIGN_ANALYZE => 'Analyze campaign performance and trends',
            self::CAMPAIGN_BUDGET_PACE => 'Manage budget pacing across campaigns',
            self::CAMPAIGN_CREATIVE_TEST => 'Run creative A/B tests',
            self::CAMPAIGN_AUDIENCE_EXPAND => 'Expand and refine target audiences',
            self::CLIENT_HEALTH_SCORE => 'Calculate client health scores',
            self::CLIENT_CHURN_PREDICT => 'Predict client churn risk',
            self::CLIENT_UPSELL_IDENTIFY => 'Identify upsell opportunities',
            self::CLIENT_REPORT_GENERATE => 'Generate client reports',
            self::CREATIVE_PERFORMANCE_SCORE => 'Score creative performance',
            self::CREATIVE_FATIGUE_DETECT => 'Detect creative fatigue',
            self::CREATIVE_VARIANT_GENERATE => 'Generate creative variants',
            self::CREATIVE_ASSET_MANAGE => 'Manage creative assets',
            self::ATTRIBUTION_CALCULATE => 'Calculate attribution models',
            self::INCREMENTALITY_TEST => 'Run incrementality experiments',
            self::FORECAST_PREDICT => 'Predict future performance',
            self::ANOMALY_DETECT => 'Detect performance anomalies',
            self::RULE_EVALUATE => 'Evaluate automation rules',
            self::WORKFLOW_EXECUTE => 'Execute workflow automations',
            self::TRIGGER_ACTION => 'Trigger actions based on events',
            self::SCHEDULE_TASK => 'Schedule recurring tasks',
            self::META_API_SYNC => 'Sync with Meta Ads API',
            self::GOOGLE_API_SYNC => 'Sync with Google Ads API',
            self::LINKEDIN_API_SYNC => 'Sync with LinkedIn Ads API',
            self::TIKTOK_API_SYNC => 'Sync with TikTok Ads API',
            self::GA4_API_SYNC => 'Sync with Google Analytics 4',
            self::PATTERN_RECOGNIZE => 'Recognize patterns in data',
            self::RECOMMENDATION_GENERATE => 'Generate actionable recommendations',
            self::DECISION_MAKE => 'Make autonomous decisions',
            self::LEARNING_UPDATE => 'Update models from outcomes',
        };
    }
}
