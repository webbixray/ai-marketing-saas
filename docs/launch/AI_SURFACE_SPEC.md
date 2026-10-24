# AI Surface — Development Spec (AI Marketing SaaS)

**Why:** Product is named "AI Marketing SaaS" but has NO user-facing AI capability.
`app/Services/AI/LLMService.php` is dead code: it imports `OpenAI\Laravel\AI\AI` which is NOT in composer.json (instantiation = fatal), and reads config keys (`ai.default_provider`, `ai.models.*`) that don't match `config/ai.php` (actual: `ai.llm.default_provider`). No AI routes exist.

**Goal:** Ship a working, tested, tenant-scoped AI content + insights surface without adding vendor dependencies.

## Architecture (self-contained, provider-abstracted)

### 1. Provider layer — `app/Services/AI/Providers/`
- `LlmProvider` (interface): `chat(array $messages, array $options): array` (returns `['choices' => [['message' => ['content' => ...]]], 'usage' => [...]]`)
- `OpenAiCompatibleProvider` — uses Laravel `Http` client; base_url + api_key + model from config; timeout/retries from config; supports OpenAI, Ollama, vLLM, any compatible endpoint. 429/5xx → retry w/ backoff, then throw `LlmException` with clean message.
- `MockProvider` — deterministic canned responses per content type (used by tests + UAT; enables zero-cost pilot).
- `LlmProviderFactory::make(?string $provider = null)` — resolves `config('ai.llm.default_provider')` ('mock' in testing env by default).

### 2. Config — `config/ai.php` (restructure)
```php
'ai' => ['llm' => [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'), // 'openai'|'mock'
    'timeout' => env('AI_TIMEOUT', 60),
    'max_retries' => env('AI_MAX_RETRIES', 2),
    'providers' => [
        'openai' => ['base_url' => env('AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                     'api_key' => env('AI_OPENAI_API_KEY'),
                     'model' => env('AI_OPENAI_MODEL', 'gpt-4o-mini')],
        'mock' => ['model' => 'mock-1'],
    ],
]],
```

### 3. Data — migration `create_ai_generations_table`
`id, tenant_id (FK), user_id (FK nullable), type (string: ad_copy|email|social_caption|blog|landing_page), brief (text), tone (string nullable), platform (string nullable), model (string), provider (string), prompt (text), output (text), input_tokens int null, output_tokens int null, status (pending|completed|failed), error (text null), timestamps`
- `AiGeneration` model: `BelongsToTenant`-style TenantScope (same global scope as Campaign), fillable as above.

### 4. Service — `app/Services/AI/AiContentService`
- `generate(string $type, string $brief, array $opts, int $tenantId, ?int $userId): AiGeneration`
  - Prompt templates per type (system prompt enforces output format + brand-safety)
  - Creates log row (pending) → calls provider → updates (completed/failed) — failures never 500 the API; return 502 with error message after logging.
- `insightsForCampaign(Campaign $campaign): array` — rule-based (no LLM): budget pacing vs elapsed time, CTR vs channel benchmark, status age alerts, spend concentration. Deterministic, fully testable.

### 5. Routes (routes/api.php, inside existing auth groups) — `AiContentController`
- `POST /api/v1/ai/generate` (auth:sanctum, throttle:api) — body: `{type, brief, tone?, platform?, max_tokens?}`
- `GET /api/v1/ai/generations` (auth:sanctum, paginate, own-tenant via scope)
- `GET /api/v1/ai/insights/campaigns/{campaign}` (auth:sanctum; tenant scope yields 404 cross-tenant)

### 6. Tests (`tests/Feature/AiContentTest.php` — mock provider, no network)
- generate ad_copy → 201, row logged (tenant_id, type, output non-empty)
- generate email w/ invalid type → 422
- provider failure (force exception) → 502 + failed log row
- generations list shows only own tenant (cross-tenant 404)
- insights endpoint returns structured rules for a campaign with data; empty campaign → empty insights
- guest → 401
- **No real API keys required anywhere; tests never call the network.**

## Acceptance
- `vendor/bin/phpunit --no-coverage` fully green including new tests
- Live smoke: with AI_DEFAULT_PROVIDER=mock, POST /ai/generate returns generated copy end-to-end
- Tenant isolation regression: AiGeneration scope covered by TenantIsolationTest extension (add AiGeneration cross-tenant read test)

## Out of scope (v1)
- Image generation, streaming responses, per-tenant model selection, cost billing by token (log token counts now; bill later)
