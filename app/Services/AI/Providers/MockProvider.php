<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Exceptions\LlmException;

/**
 * Deterministic, zero-network mock provider (tests + zero-cost pilot).
 *
 * Pure PHP on purpose: no Laravel facades, so it unit-tests without an app
 * instance. Responses are canned per content type and deterministic —
 * same input always yields the same output (no randomness).
 */
class MockProvider implements LlmProvider
{
    public const NAME = 'mock';
    public const MODEL = 'mock-1';

    protected array $templates = [
        'ad_copy' => "Headline: Drive more growth with a campaign that converts\n\n"
            . "Body: Tired of wasting ad spend? Our AI-optimized campaigns reach the right audience at the right moment, "
            . "with creative that matches intent. Launch in minutes, not weeks.\n\n"
            . "CTA: Start your free pilot today — no credit card required.",
        'email' => "Subject: Your campaign could be doing better\n\n"
            . "Hi there,\n\n"
            . "We analyzed your recent performance and found room to improve delivery and clicks. "
            . "Our optimization engine can rebalance your budget automatically.\n\n"
            . "See the full breakdown in your dashboard.\n\n"
            . "Best regards,\n"
            . "The Growth Team",
        'social_caption' => "Big news: your next campaign is ready to launch 🚀\n\n"
            . "AI-optimized targeting. Real-time pacing. Zero guesswork.\n\n"
            . "#marketing #ai #growth",
        'blog' => "The state of AI-assisted marketing in 2026\n\n"
            . "Modern marketing teams run hundreds of campaigns at once. "
            . "The ones winning are using automated optimization: budget pacing, "
            . "creative testing, and audience refinement driven by data rather than instinct.\n\n"
            . "In this post we break down the four levers that matter most: pacing, CTR, "
            . "spend concentration, and status hygiene — and how to automate each one.",
        'landing_page' => "Headline: Marketing on autopilot, results on your terms\n\n"
            . "Subheadline: Launch, optimize, and scale campaigns with an AI engine built for agencies.\n\n"
            . "Features:\n"
            . "- Automated budget pacing against your flight dates\n"
            . "- Real-time CTR benchmarking per channel\n"
            . "- One-click campaign insights and recommendations\n\n"
            . "CTA: Book a demo — see your first report in under five minutes.",
    ];

    public function name(): string
    {
        return self::NAME;
    }

    public function chat(array $messages, array $options = []): array
    {
        if (empty($messages)) {
            throw new LlmException('Mock provider received no messages.');
        }

        $type = $options['type'] ?? $this->inferType($messages);
        $brief = $this->lastUserContent($messages);

        $content = $this->templates[$type] ?? $this->genericResponse($brief);

        return [
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $content],
                ],
            ],
            'usage' => [
                'prompt_tokens' => $this->estimateTokens($messages),
                'completion_tokens' => $this->estimateTokens([['content' => $content]]),
            ],
            'model' => self::MODEL,
        ];
    }

    /**
     * Deterministic word-based token estimate (~4 chars per word, min 1).
     */
    protected function estimateTokens(array $messages): int
    {
        $words = 0;
        foreach ($messages as $message) {
            $words += str_word_count((string) ($message['content'] ?? ''));
        }

        return max(1, (int) floor($words / 4));
    }

    protected function lastUserContent(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? null) === 'user') {
                return (string) ($messages[$i]['content'] ?? '');
            }
        }

        return '';
    }

    protected function genericResponse(string $brief): string
    {
        $label = $brief === '' ? 'request' : $brief;

        return "Here is a draft response for: {$label}\n\n"
            . "This is deterministic mock output — configure a real provider in ai.llm to generate live content.";
    }

    /**
     * Best-effort content-type inference from the prompt text (used when
     * the caller did not pass an explicit 'type' option).
     */
    protected function inferType(array $messages): ?string
    {
        $text = strtolower(implode(' ', array_column($messages, 'content')));

        foreach (array_keys($this->templates) as $type) {
            if (str_contains($text, $type)) {
                return $type;
            }
        }

        return null;
    }
}
