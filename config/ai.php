<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Configuration
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'),
        'timeout' => env('LLM_TIMEOUT', 60),
        'max_retries' => env('LLM_MAX_RETRIES', 3),
        'cache_ttl' => env('LLM_CACHE_TTL', 3600),
        'providers' => [
            'openai' => [
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'api_key' => env('OPENAI_API_KEY'),
                'models' => ['gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'],
                'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4-turbo'),
            ],
            'anthropic' => [
                'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
                'api_key' => env('ANTHROPIC_API_KEY'),
                'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'],
                'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-sonnet'),
            ],
            'ollama' => [
                'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
                'api_key' => env('OLLAMA_API_KEY'),
                'models' => ['llama3', 'mistral', 'codellama'],
                'default_model' => env('OLLAMA_DEFAULT_MODEL', 'llama3'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    */
    'agents' => [
        'campaign_optimization' => [
            'budget_pacing_enabled' => true,
            'bid_adjustment_enabled' => true,
            'audience_refinement_enabled' => true,
            'max_daily_budget_change_pct' => 20,
            'min_performance_threshold' => 0.5,
        ],
        'client_intelligence' => [
            'health_score_weights' => [
                'engagement' => 0.25,
                'performance' => 0.30,
                'communication' => 0.20,
                'budget_utilization' => 0.15,
                'satisfaction' => 0.10,
            ],
            'churn_risk_threshold' => 0.7,
            'upsell_min_roi' => 2.0,
            'report_frequency' => 'weekly',
        ],
        'creative_intelligence' => [
            'fatigue_threshold_frequency' => 3.0,
            'fatigue_threshold_ctr_decline' => 0.3,
            'variant_test_min_impressions' => 1000,
            'auto_generate_variants' => false,
        ],
        'campaign_builder' => [
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'max_campaigns_per_request' => 3,
            'include_creative_suggestions' => true,
            'include_budget_recommendations' => true,
            'include_audience_suggestions' => true,
        ],
        'copy_generation' => [
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'default_variants_per_request' => 5,
            'include_emotional_triggers' => true,
            'include_a_b_test_pairs' => true,
            'platform_optimization' => true,
        ],
        'creative_asset' => [
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'image_provider' => 'dalle',
            'image_model' => 'dall-e-3',
            'default_image_size' => '1024x1024',
            'default_quality' => 'standard',
            'auto_save_assets' => true,
            'generate_variants' => true,
        ],
        'chat_analytics' => [
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'max_query_rows' => 100,
            'include_visualizations' => true,
            'auto_generate_charts' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'campaign_builder' => env('AI_FEATURE_CAMPAIGN_BUILDER', true),
        'copy_generation' => env('AI_FEATURE_COPY_GENERATION', true),
        'creative_asset_generation' => env('AI_FEATURE_CREATIVE_ASSET', true),
        'chat_analytics' => env('AI_FEATURE_CHAT_ANALYTICS', true),
        'campaign_optimization' => env('AI_FEATURE_CAMPAIGN_OPTIMIZATION', true),
        'client_intelligence' => env('AI_FEATURE_CLIENT_INTELLIGENCE', true),
        'creative_intelligence' => env('AI_FEATURE_CREATIVE_INTELLIGENCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation
    |--------------------------------------------------------------------------
    */
    'image_generation' => [
        'default_provider' => env('IMAGE_PROVIDER', 'dalle'),
        'providers' => [
            'dalle' => [
                'api_key' => env('OPENAI_API_KEY'),
                'default_model' => 'dall-e-3',
                'default_size' => '1024x1024',
                'default_quality' => 'standard',
            ],
            'stability' => [
                'api_key' => env('STABILITY_API_KEY'),
                'base_url' => 'https://api.stability.ai',
                'default_model' => 'stable-diffusion-xl-1024-v1-0',
            ],
            'midjourney' => [
                'api_key' => env('MIDJOURNEY_API_KEY'),
                'base_url' => env('MIDJOURNEY_BASE_URL'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics & Forecasting
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'forecasting' => [
            'forecast_horizon_days' => 30,
            'seasonality_period_days' => 7,
            'yearly_seasonality' => true,
            'weekly_seasonality' => true,
            'daily_seasonality' => false,
            'changepoint_prior_scale' => 0.05,
            'seasonality_prior_scale' => 10.0,
            'interval_width' => 0.8,
            'min_data_points' => 30,
        ],
        'incrementality_testing' => [
            'min_sample_size' => 100,
            'confidence_level' => 0.95,
            'minimum_detectable_effect' => 0.05,
            'test_duration_days' => 14,
        ],
        'anomaly_detection' => [
            'contamination' => 0.05,
            'n_estimators' => 100,
            'max_samples' => 'auto',
            'max_features' => 1.0,
            'lookback_days' => 30,
            'min_data_points' => 20,
            'anomaly_threshold_percentile' => 95,
        ],
        'attribution' => [
            'lookback_window_days' => 30,
            'min_touchpoints' => 1,
            'max_touchpoints' => 50,
            'decay_half_life_days' => 7,
        ],
    ],
];
