<?php

namespace App\Agents\AI;

use App\Agents\BaseAgent;

class ReportCopilotAgent extends BaseAgent
{
    protected $name = 'ReportCopilot';

    protected function systemPrompt(): string
    {
        return <<<PROMPT
You are an Enterprise Marketing Reporting AI Copilot. Your role is to help marketing executives and teams get actionable insights from their marketing data using natural language while maintaining enterprise governance standards.

CORE PRINCIPLES:
1. Always ground insights in actual data, not just AI hallucinations
2. Provide clear root-cause hypotheses with supporting evidence and confidence scores
3. Recommend specific next actions with expected impact assessment
4. Maintain brand safety and compliance in all analyses
5. Confidence score for every insight (0-100) with human review recommendation
6. Track data sources for transparency and accountability
7. Output structured JSON for machine consumption and audit trails
8. Flag any uncertainties or data gaps with explicit disclosure

RULES:
- Output valid JSON only
- Include data sources referenced for each insight
- Provide confidence scores (0-100) for all analyses
- Flag any uncertainties or data gaps
- Always include human review recommendation for strategic insights
- Never make definitive causal claims without data support
- Include brand safety scoring (0-100) for all content-related analyses
- Identify compliance flags that may require legal review
- Track prompt execution for accountability

PROMPT;
    }

    public function analyze(string $naturalLanguageQuery, array $context = []): array
    {
        $prompt = <<<PROMPT
Analyze the following natural language query against marketing campaign data:

NATURAL LANGUAGE QUERY: "{$naturalLanguageQuery}"

CONTEXT AVAILABLE:
- Tenant ID: {$context['tenant_id'] ?? 'current_user_tenant'}
- Available Data: Campaigns, Clients, Conversion Events, Touchpoints, Feature Flags, Attribution records
- Time Period: {$context['period'] ?? 'last 30 days'}
- Channels: {$context['channels'] ?? 'all'}
- Attribution Model: {$context['attribution_model'] ?? 'last_click'}

Provide comprehensive analysis as JSON with these sections (ALL REQUIRED):
1. summary: Plain language summary of what the data shows (required)
2. key_metrics: Relevant KPI values with numbers and confidence scores (required)
3. trend_analysis: What's happening over time (improving, declining, stable) with confidence (required)
4. root_cause_hypotheses: Top 3-5 hypotheses explaining the patterns, WITH confidence scores for each (required)
5. data_insights: Specific data points driving the analysis WITH data source references (required)
6. recommendations: 3-5 specific, actionable recommendations WITH expected impact and confidence (required)
7. next_actions: Immediate next steps to take WITH responsible parties (required)
8. confidence: Overall AI confidence 0-100 (required)
9. data_sources: What data was used for this analysis WITH specific table/field names (required)
10. human_review_needed: Boolean - whether human strategic review is recommended (required)
11. brand_safety_score: 0-100 score for any content-related analysis (required when applicable)
12. compliance_flags: Any compliance concerns WITH specific regulations referenced (required when applicable)
13. attribution_model: Which attribution model was applied (required)
14. period_analyzed: The time period this analysis covers (required)

Be specific, data-informed, enterprise-ready, and governance-compliant. Output ONLY valid JSON.
PROMPT;

        return $this->callLLM($prompt);
    }

    public function executiveSummary(array $context = []): array
    {
        $prompt = <<<PROMPT
Generate an executive marketing summary for the current period.

CONTEXT:
- Period: {$context['period'] ?? 'last 30 days'}
- Channels: {$context['channels'] ?? 'all channels'}
- Focus: {$context['focus'] ?? 'overall performance'}
- Metrics of interest: {$context['metrics'] ?? 'spend, revenue, ROAS, CAC, conversion rate'}
- Include governance metadata: required

Provide JSON output with these sections (ALL REQUIRED):
1. executive_summary: 2-3 paragraph summary for CMO/leadership WITH governance tag (required)
2. headline_metrics: Key numbers in bullet format WITH confidence scores (required)
3. top_performing_campaigns: Top 3 campaigns with performance AND brand_safety_score (required)
4. underperforming_campaigns: Bottom 3 campaigns with performance AND compliance_flags (required)
5. budget_efficiency: Analysis of spend vs. results WITH confidence score (required)
6. growth_opportunities: 2-3 opportunities for growth WITH prioritization (required)
7. risks_warnings: 2-3 potential risks to address WITH mitigation recommendations (required)
8. recommended_next_steps: 3-5 priority actions WITH responsible parties (required)
9. confidence: AI confidence 0-100 (required)
10. human_review_needed: Boolean (required)
11. brand_safety_overall: Overall brand safety score 0-100 (required)
12. compliance_status: High-level compliance status (required)

Be concise, executive-focused, data-informed, and governance-compliant. Output ONLY valid JSON.
PROMPT;

        return $this->callLLM($prompt);
    }

    public function comparePeriods(string $primaryPeriod, string $comparisonPeriod, array $context = []): array
    {
        $prompt = <<<PROMPT
Compare marketing performance between two periods.

PRIMARY PERIOD: {$primaryPeriod} (e.g., "last 30 days")
COMPARISON PERIOD: {$comparisonPeriod} (e.g., "previous 30 days")

CONTEXT:
- Metrics to compare: {$context['metrics'] ?? 'spend, revenue, ROAS, CAC, conversion rate, clicks, impressions'}
- Channels: {$context['channels'] ?? 'all'}
- Business impact: {$context['business_impact'] ?? 'ROI and growth'}
- Include governance metadata: required

Provide JSON output with these sections (ALL REQUIRED):
1. comparison_table: Side-by-side metric comparison WITH p-values where applicable (required)
2. percentage_changes: % change for each metric WITH confidence intervals (required)
3. statistically_significant: Which changes are meaningful WITH statistical justification (required)
4. key_differences: What's different between periods WITH evidence (required)
5. cause_hypotheses: Why the performance changed WITH confidence scores (required)
6. recommendations: Actions based on the comparison WITH expected impact (required)
7. confidence: AI confidence 0-100 (required)
8. human_review_needed: Boolean (required)
9. attribution_model_comparison: How the attribution model differed between periods (required)
10. data_quality_comparison: Comparison of data quality between periods (required)

Be data-informed and practical. Output ONLY valid JSON.
PROMPT;

        return $this->callLLM($prompt);
    }

    public function brandSafetyFilter(string $content, string $brandVoice = 'corporate', array $restrictions = []): array
    {
        $prompt = <<<PROMPT
Filter content for brand safety and compliance.

CONTENT TO REVIEW:
{$content}

BRAND VOICE: {$brandVoice}

RESTRICTIONS: {$restrictions['allowed_topics'] ?? 'None specified'}
DISALLOWED_TOPICS: {$restrictions['disallowed_topics'] ?? 'None specified'}
INDUSTRY: {$restrictions['industry'] ?? 'General'}
TARGET_AUDIENCE: {$restrictions['target_audience'] ?? 'General'}

Provide JSON output with:
1. brand_safety_score: 0-100 score (required)
2. compliance_flags: List of compliance concerns WITH specific regulation references (required)
3. tone_assessment: Whether the tone matches the brand voice (required)
4. risk_areas: Specific areas of risk WITH severity levels (required)
5. recommended_edits: Specific edits needed WITH rationale (required)
6. confidence: AI confidence 0-100 (required)
7. human_review_recommended: Boolean (required)

Be thorough and enterprise-ready. Output ONLY valid JSON.
PROMPT;

        return $this->callLLM($prompt);
    }

    public function promptLogging(string $query, array $context, array $result): array
    {
        $prompt = <<<PROMPT
Log this AI interaction for governance and accountability.

ORIGINAL QUERY: {$query}

CONTEXT: " . json_encode($context) . "

RESULT HIGHLIGHTS: " . json_encode(array_slice(array_values($result), 0, 5)) . "

Provide JSON output with:
1. query_id: Unique identifier for this query (required)
2. timestamp: When this query was executed (required)
3. query_text: The original natural language query (required)
4. context_summary: Summary of the context used (required)
5. key_results: Summary of the top 3 results (required)
6. confidence_range: min-max confidence scores (required)
7. data_sources_referenced: List of data tables/fields used (required)
8. human_review_flag: Whether this output needs human review (required)
9. compliance_status: Initial compliance assessment (required)
10. retention_period: How long this log should be retained (required)

Be thorough for governance compliance. Output ONLY valid JSON.
PROMPT;

        return $this->callLLM($prompt);
    }
}