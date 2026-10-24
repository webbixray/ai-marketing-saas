<?php

namespace App\Services\AI;

use App\Models\AiGeneration;
use App\Models\Campaign;
use App\Services\AI\Exceptions\LlmException;
use App\Services\AI\Providers\LlmProvider;
use App\Services\AI\Providers\LlmProviderFactory;

/**
 * AI content generation + rule-based campaign insights.
 *
 * generate() creates a pending AiGeneration row, calls the configured LLM
 * provider, and records the outcome. Provider failures mark the row failed
 * and rethrow LlmException — the controller turns that into a 502, so a
 * broken provider never 500s the API.
 */
class AiContentService
{
    public const TYPES = ['ad_copy', 'email', 'social_caption', 'blog', 'landing_page'];

    protected LlmProvider $provider;

    public function __construct(?LlmProvider $provider = null)
    {
        $this->provider = $provider ?? LlmProviderFactory::make();
    }

    /**
     * Generate AI content for the given type.
     *
     * @throws LlmException when the provider fails (row is left status=failed)
     */
    public function generate(string $type, string $brief, array $opts = [], int $tenantId, ?int $userId = null): AiGeneration
    {
        $system = self::systemPrompt($type);
        $user = self::buildUserPrompt($type, $brief, $opts);

        $generation = AiGeneration::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'brief' => $brief,
            'tone' => $opts['tone'] ?? null,
            'platform' => $opts['platform'] ?? null,
            'model' => $this->provider->name(), // overwritten with provider-reported model on success
            'provider' => $this->provider->name(),
            'prompt' => $system . "\n\n" . $user,
            'status' => 'pending',
        ]);

        try {
            $response = $this->provider->chat(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                [
                    'type' => $type,
                    'max_tokens' => (int) ($opts['max_tokens'] ?? 1000),
                ]
            );

            $content = $response['choices'][0]['message']['content'] ?? null;
            if (! is_string($content) || trim($content) === '') {
                throw new LlmException('Provider returned an empty response.');
            }

            $usage = $response['usage'] ?? [];

            $generation->update([
                'status' => 'completed',
                'output' => $content,
                'model' => $response['model'] ?? $generation->model,
                'input_tokens' => $usage['prompt_tokens'] ?? null,
                'output_tokens' => $usage['completion_tokens'] ?? null,
            ]);

            return $generation->fresh();
        } catch (LlmException $e) {
            $generation->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Rule-based (deterministic, LLM-free) campaign insights:
     * budget pacing vs elapsed time, CTR vs channel benchmark, status age
     * alerts, and spend without conversions.
     *
     * @return array<int, array{type: string, severity: string, message: string, details: array<string, mixed>}>
     */
    public function insightsForCampaign(Campaign $campaign): array
    {
        $insights = [];

        // 1. Budget pacing vs elapsed time
        if ((float) $campaign->budget > 0 && (float) $campaign->spent > 0) {
            $budgetPct = (float) $campaign->spent / (float) $campaign->budget;

            $start = $campaign->start_date ? $campaign->start_date->copy()->startOfDay() : ($campaign->created_at ? $campaign->created_at->copy()->startOfDay() : null);
            $end = $campaign->end_date ? $campaign->end_date->copy()->endOfDay() : now();

            if ($start && $end->gte($start)) {
                $totalDays = max(1.0, (float) $start->diffInDays($end));
                $elapsedDays = max(0.0, min($totalDays, (float) $start->diffInDays(now())));
                $elapsedPct = $elapsedDays / $totalDays;

                if ($budgetPct >= 0.9) {
                    $insights[] = [
                        'type' => 'budget',
                        'severity' => 'warning',
                        'message' => sprintf('Campaign has consumed %.0f%% of its budget.', $budgetPct * 100),
                        'details' => ['budget' => (float) $campaign->budget, 'spent' => (float) $campaign->spent],
                    ];
                } elseif ($budgetPct < $elapsedPct - 0.25) {
                    $insights[] = [
                        'type' => 'under_pacing',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Spend is behind flight progress: %.0f%% of budget used after %.0f%% of the flight.',
                            $budgetPct * 100,
                            $elapsedPct * 100
                        ),
                        'details' => ['budget_pct' => round($budgetPct * 100), 'elapsed_pct' => round($elapsedPct * 100)],
                    ];
                } elseif ($budgetPct > $elapsedPct + 0.25) {
                    $insights[] = [
                        'type' => 'over_pacing',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Spend is ahead of flight progress: %.0f%% of budget used after %.0f%% of the flight.',
                            $budgetPct * 100,
                            $elapsedPct * 100
                        ),
                        'details' => ['budget_pct' => round($budgetPct * 100), 'elapsed_pct' => round($elapsedPct * 100)],
                    ];
                }
            }
        }

        // 2. CTR vs channel benchmark
        if ((int) $campaign->impressions > 0 && (int) $campaign->clicks > 0) {
            $ctr = ((int) $campaign->clicks / (int) $campaign->impressions) * 100;
            $benchmark = self::ctrBenchmark($campaign->platform);

            if ($benchmark !== null && $ctr < $benchmark * 0.5) {
                $insights[] = [
                    'type' => 'low_ctr',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'CTR %.2f%% is well below the %s benchmark of ~%.2f%%.',
                        $ctr,
                        (string) $campaign->platform,
                        $benchmark
                    ),
                    'details' => ['ctr' => round($ctr, 2), 'benchmark' => $benchmark],
                ];
            }
        }

        // 3. Status age: active campaign with no tracked spend
        if ($campaign->status === 'active' && (float) $campaign->spent <= 0
            && $campaign->created_at && $campaign->created_at->lt(now()->subDays(14))) {
            $insights[] = [
                'type' => 'stale_campaign',
                'severity' => 'info',
                'message' => 'Active campaign shows no spend after 14+ days; verify delivery is live.',
                'details' => ['created_at' => $campaign->created_at->toDateString()],
            ];
        }

        // 4. Spend concentration: meaningful spend with no conversions
        if ((float) $campaign->spent >= 100 && (int) $campaign->conversions === 0 && (int) $campaign->impressions >= 1000) {
            $insights[] = [
                'type' => 'spend_without_conversions',
                'severity' => 'critical',
                'message' => sprintf('Campaign spent %.2f with no recorded conversions.', (float) $campaign->spent),
                'details' => ['spent' => (float) $campaign->spent, 'impressions' => (int) $campaign->impressions],
            ];
        }

        return $insights;
    }

    /**
     * Rough channel CTR benchmarks (percent) for the low-CTR rule, keyed by
     * the campaigns.platform enum values (see add_marketing_fields migration).
     */
    protected static function ctrBenchmark(?string $platform): ?float
    {
        $benchmarks = [
            'meta' => 1.0,
            'google' => 3.0,
            'tiktok' => 1.5,
            'linkedin' => 0.6,
            'twitter' => 0.05,
            'email' => 2.5,
        ];

        $key = strtolower((string) $platform);

        return $benchmarks[$key] ?? null;
    }

    /**
     * Per-type system prompt: enforces output shape + brand safety.
     */
    public static function systemPrompt(string $type): string
    {
        $format = match ($type) {
            'ad_copy' => 'Return ad copy with three labeled sections: "Headline:", "Body:", and "CTA:". Keep the headline under 10 words.',
            'email' => 'Return a marketing email with a "Subject:" line, a short greeting, 2-3 concise paragraphs, and a sign-off.',
            'social_caption' => 'Return a single social media caption (max 125 characters) plus up to 3 relevant hashtags on a final line.',
            'blog' => 'Return a blog post with a title line and 3-4 short paragraphs.',
            'landing_page' => 'Return landing page copy with "Headline:", "Subheadline:", up to 3 bulleted features, and a "CTA:" line.',
            default => 'Return concise marketing copy that matches the request.',
        };

        return "You are an expert direct-response marketing copywriter for an agency SaaS. "
            . "Write persuasive, professional copy. Never use profanity, make unsupported superlative claims, "
            . "or include discriminatory content. "
            . $format;
    }

    public static function buildUserPrompt(string $type, string $brief, array $opts): string
    {
        $parts = ["Content type: {$type}", "Brief: {$brief}"];

        if (! empty($opts['tone'])) {
            $parts[] = "Tone: {$opts['tone']}";
        }
        if (! empty($opts['platform'])) {
            $parts[] = "Target platform: {$opts['platform']}";
        }

        return implode("\n", $parts);
    }
}
