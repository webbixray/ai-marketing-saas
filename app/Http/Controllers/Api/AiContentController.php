<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiGeneration;
use App\Models\Campaign;
use App\Services\AI\AiContentService;
use App\Services\AI\Exceptions\LlmException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group AI
 *
 * AI content generation and campaign insights.
 *
 * Provider failures surface as 502 (with the generation row logged as
 * failed) — never 500. GET /ai/status reports configured providers without
 * ever exposing API keys.
 */
class AiContentController extends Controller
{
    /**
     * POST /api/v1/ai/generate
     */
    public function generate(Request $request)
    {
        $user = $request->user();

        // The ai_generations table is tenant-scoped and requires a tenant.
        if (! $user->tenant_id) {
            return response()->json([
                'message' => 'AI generation requires a tenant. This account is not associated with one.',
            ], 422);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(AiContentService::TYPES)],
            'brief' => ['required', 'string', 'max:5000'],
            'tone' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:50',
            'max_tokens' => 'nullable|integer|min:1|max:4000',
        ]);

        try {
            $generation = app(AiContentService::class)->generate(
                $validated['type'],
                $validated['brief'],
                $validated,
                (int) $user->tenant_id,
                $user->id
            );
        } catch (LlmException $e) {
            return response()->json([
                'message' => 'AI generation failed: ' . $e->getMessage(),
            ], 502);
        }

        return response()->json($generation, 201);
    }

    /**
     * GET /api/v1/ai/generations — paginated, tenant-scoped via global scope.
     */
    public function index(Request $request)
    {
        $generations = AiGeneration::query()
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json($generations);
    }

    /**
     * GET /api/v1/ai/status — configured providers. Never leaks API keys.
     */
    public function status()
    {
        $providers = [];

        foreach ((array) config('ai.llm.providers', []) as $name => $config) {
            $providers[] = [
                'name' => $name,
                'model' => $config['model'] ?? null,
                'configured' => $name === 'mock' ? true : ! empty($config['api_key']),
            ];
        }

        return response()->json([
            'default_provider' => config('ai.llm.default_provider'),
            'timeout' => config('ai.llm.timeout'),
            'providers' => $providers,
        ]);
    }

    /**
     * GET /api/v1/ai/insights/campaigns/{campaign} — rule-based insights.
     * Tenant scope yields 404 for cross-tenant campaigns.
     */
    public function insights(Campaign $campaign)
    {
        return response()->json([
            'campaign' => $campaign->only(['id', 'name', 'platform', 'status']),
            'insights' => app(AiContentService::class)->insightsForCampaign($campaign),
        ]);
    }
}
